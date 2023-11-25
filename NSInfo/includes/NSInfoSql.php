<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Handles all SQL-related functions for NSInfo.
 */
class NSInfoSql
{
	#region Public Constants
	public const FIELD_INDEX = 'nsIndex';
	public const FIELD_PAGENAME = 'nsPageName';
	public const FIELD_BASE = 'nsBase';
	public const FIELD_CATEGORY = 'nsCategory';
	public const FIELD_GAMESPACE = 'nsGamespace';
	public const FIELD_ID = 'nsId';
	public const FIELD_MAIN_PAGE = 'nsMainPage';
	public const FIELD_NAME = 'nsName';
	public const FIELD_PARENT = 'nsParent';
	public const FIELD_TRAIL = 'nsTrail';

	public const TABLE_INFO = 'nsInfo';
	#endregion

	#region Private Static Variables
	/** @var NSInfoSql */
	private static $instance;
	#endregion

	#region Private Variables
	/** @var IDatabase */
	private $dbRead;

	/** @var IDatabase */
	private $dbWrite;
	#endregion

	#region Constructor (private)
	/**
	 * Creates an instance of the NSInfoSql class.
	 */
	private function __construct()
	{
		$dbWriteConst = defined('DB_PRIMARY') ? 'DB_PRIMARY' : 'DB_MASTER';
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$this->dbRead = $lb->getConnectionRef(DB_REPLICA);

		// We get dbWrite lazily since writing will often be unnecessary.
		$this->dbWrite = $lb->getLazyConnectionRef(constant($dbWriteConst));
	}
	#endregion

	#region Public Static Functions
	/**
	 * Gets the global singleton instance of the class.
	 *
	 * @return NSInfoSql
	 */
	public static function getInstance(): NSInfoSql
	{
		if (!isset(self::$instance)) {
			self::$instance = new self;
		}

		return self::$instance;
	}
	#endregion

	#region Public Functions

	public function deleteNamespace(int $index, string $pageName): void
	{
		$this->dbWrite->delete(self::TABLE_INFO, [self::FIELD_INDEX => $index, self::FIELD_PAGENAME => $pageName], __METHOD__);

		if ($this->dbWrite->affectedRows() === 0) {
			$status->fatal('interwiki_delfailed', $prefix);
		} else {
			$this->getOutput()->addWikiMsg('interwiki_deleted', $prefix);
			$log = new LogPage('interwiki');
			$log->addEntry(
				'iw_delete',
				$selfTitle,
				$reason,
				[$prefix],
				$this->getUser()
			);
			$lookup->invalidateCache($prefix);
		}
	}

	public function getNamespaceInfo(): array
	{
		/** @var NSInfoNamespace[] $retval */
		$retval = [];
		$fields = [
			self::FIELD_INDEX,
			self::FIELD_PAGENAME,
			self::FIELD_BASE,
			self::FIELD_CATEGORY,
			self::FIELD_GAMESPACE,
			self::FIELD_ID,
			self::FIELD_MAIN_PAGE,
			self::FIELD_NAME,
			self::FIELD_PARENT,
			self::FIELD_TRAIL
		];

		// We include ORDER BY strictly to ensure that the root namespace, if there is one, gets created before any subspaces.
		$options = ['ORDER BY' => [
			self::FIELD_INDEX,
			self::FIELD_PAGENAME
		]];
		$rows = $this->dbRead->select(self::TABLE_INFO, $fields, '', __METHOD__, $options);
		if ($rows) {
			for ($row = $rows->fetchRow(); $row; $row = $rows->fetchRow()) {
				$nsInfo = NSInfoNamespace::fromRow($row);
				$index = $nsInfo->getNsId();
				$pageName = $nsInfo->getPageName();
				if (strlen($pageName)) {
					if (!isset($retval[$index])) {
						$retval[$index] = NSInfoNamespace::fromNamespace($index);
					}

					$retval[$index]->addSubSpace($nsInfo);
				} else {
					$retval[$index] = $nsInfo;
				}

				$retval[strtolower($nsInfo->getId())] = $nsInfo;
				$retval[strtolower($nsInfo->getBase())] = $nsInfo;
			}
		}

		return $retval;
	}

	/**
	 * Migrates the UespCustomCode data to the database table.
	 *
	 * @param DatabaseUpdater $updater
	 * @param string $dir
	 */
	public function migrateTables(): void
	{
		$msg = new Message('Uespnamespacelist');
		if (!$msg->exists()) {
			return;
		}

		$lines = explode("\n", $msg->inContentLanguage()->text());
		#RHDebug::echo($lines);
		$user = User::newSystemUser('MediaWiki default', ['steal' => true]);
		foreach ($lines as $line) {
			$line = preg_replace('/\s*<\s*\/?\s*pre(\s+[^>]*>|>)\s*/', '', trim($line));
			if (substr($line, 0, 1) !== '#' && strlen($line) > 0) {
				$nsInfo = NSInfoNamespace::fromLine($line);
				// RHDebug::echo($nsInfo);
				$this->dbWrite->upsert(
					self::TABLE_INFO,
					[
						self::FIELD_INDEX => $nsInfo->getNsId(true),
						self::FIELD_PAGENAME => $nsInfo->getPageName(true),
						self::FIELD_BASE => $nsInfo->getBase(),
						self::FIELD_CATEGORY => $nsInfo->getCategory(),
						self::FIELD_GAMESPACE => $nsInfo->getGameSpace(),
						self::FIELD_ID => $nsInfo->getId(),
						self::FIELD_MAIN_PAGE => $nsInfo->getMainPage(),
						self::FIELD_NAME => $nsInfo->getName(),
						self::FIELD_PARENT => $nsInfo->getParent(),
						self::FIELD_TRAIL => $nsInfo->getTrail()
					],
					[self::FIELD_BASE, self::FIELD_PAGENAME],
					[
						self::FIELD_INDEX => $nsInfo->getNsId(false),
						self::FIELD_PAGENAME => $nsInfo->getPageName(false),
						self::FIELD_BASE => $nsInfo->getBase(),
						self::FIELD_CATEGORY => $nsInfo->getCategory(),
						self::FIELD_GAMESPACE => $nsInfo->getGameSpace(),
						self::FIELD_ID => $nsInfo->getId(),
						self::FIELD_MAIN_PAGE => $nsInfo->getMainPage(),
						self::FIELD_NAME => $nsInfo->getName(),
						self::FIELD_PARENT => $nsInfo->getParent(),
						self::FIELD_TRAIL => $nsInfo->getTrail()
					]
				);

				$nsInfo->savePage($user);
			}
		}
	}

	/**
	 * Migrates the old UespCustomCode data to the database table.
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return void
	 */
	public function onLoadExtensionSchemaUpdates(DatabaseUpdater $updater): void
	{
		// Initial table setup/modifications from v1.
		if (wfReadOnly()) {
			return;
		}

		/** @var string $dir  */
		$dir = dirname(__DIR__);
		$dbw = $this->dbWrite;
		if (!$dbw->tableExists(self::TABLE_INFO)) {
			$updater->addExtensionTable(self::TABLE_INFO, "$dir/sql/create-" . self::TABLE_INFO . '.sql');
		}

		$updater->addExtensionUpdate([[$this, 'migrateTables']]);
	}

	public function updateNamespace(NSInfoNamespace $nsInfo, bool $new): Status
	{
		$row = [];
		if ($new) {
			$this->dbWrite->insert('interwiki', $row, __METHOD__, ['IGNORE']);
		} else { // $do === 'edit'
			$this->dbWrite->update('interwiki', $row, [self::FIELD_INDEX => $nsInfo->getNsId(), self::FIELD_PAGENAME => $nsInfo->getPageName()], __METHOD__, ['IGNORE']);
		}

		// used here: interwiki_addfailed, interwiki_added, interwiki_edited
		if ($this->dbWrite->affectedRows() === 0) {
			$status = Status::newFatal();
			$status->fatal("interwiki_{$do}failed", $prefix);
		} else {
			$this->getOutput()->addWikiMsg("interwiki_{$do}ed", $prefix);
		}
	}
	#endregion
}
