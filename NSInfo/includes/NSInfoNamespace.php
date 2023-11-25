<?php

class NSInfoNamespace
{
	#region Private Constants
	private const TRACKING_BASE = 'MediaWiki:nsinfo-';
	#endregion

	#region Private Static Fields
	/** @var NSInfoNamespace $empty */
	private static $empty;
	#endregion

	#region Private Fields
	/** @var string $base */
	private $base = '';

	/** @var string $category */
	private $category = '';

	/** @var bool $gameSpace */
	private $gameSpace = true;

	/** @var string $id */
	private $id = '';

	/** @var string $mainPage */
	private $mainPage = '';

	/** @var string $name */
	private $name = '';

	/** @var int $nsId */
	private $nsId;

	/** @var int $originalNsId */
	private $originalNsId;

	/** @var string $originalPageName */
	private $originalPageName;

	/** @var string $pageName */
	private $pageName;

	/** @var string $parent */
	private $parent = '';

	/** @var NSInfoNamespace[] $subSpaces */
	private $subSpaces = [];

	/** @var Title $tracking */
	private $tracking = '';

	/** @var string $trail */
	private $trail = '';
	#endregion

	#region Constructor
	public function __construct($nsOrBase, $pageName = '')
	{
		global $wgContLang;

		if (is_int($nsOrBase)) {
			$this->nsId = $nsOrBase;
			$this->pageName = $pageName;
			$nsName = $wgContLang->getNsText($nsOrBase);
			$this->base = strlen($this->pageName)
				? "{$nsName}:{$this->pageName}"
				: $nsName;
		} else {
			$exploded = explode(
				':',
				$nsOrBase,
				2
			);

			$nsId = VersionHelper::getInstance()->getContentLanguage()->getNsIndex(strtr($exploded[0], ' ', '_'));
			$this->nsId = $nsId;
			$this->pageName = count($exploded) > 1 ? $exploded[1] : '';
			$this->base = $nsOrBase;
		}

		$trackingPage = self::TRACKING_BASE . $this->nsId;
		if ($this->pageName) {
			$trackingPage .= '-' . $this->pageName;
		}

		$this->tracking = Title::newFromText($trackingPage);
		$this->originalNsId = $this->nsId;
		$this->originalPageName = $this->pageName;
	}
	#endregion

	#region Public Static Functions
	public static function empty()
	{
		if (!isset(self::$empty)) {
			$nsInfo = new NSInfoNamespace(0, '');
			$nsInfo->base = '';
			$nsInfo->category = '';
			$nsInfo->id = '';
			$nsInfo->name = '';
			$nsInfo->parent = '';
			$nsInfo->mainPage = '';
			$nsInfo->trail = '';
			self::$empty = $nsInfo;
		}

		return self::$empty;
	}

	/**
	 * Creates a new nsId info from an old-style text line.
	 *
	 * @param string $line The line to parse.
	 *
	 * @return NSInfoNamespace
	 */
	public static function fromLine(string $line): NSInfoNamespace
	{
		#RHDebug::show("\n\n=== Line ===\n", $line);
		$fields = array_map('trim', explode(';', $line));
		$fieldCount = count($fields);
		$nsInfo = new NSInfoNamespace($fields[0]);
		$nsInfo->id = ($fieldCount > 1 && strlen($fields[1]) > 0)
			? $fields[1]
			: $nsInfo->getDefaultId();
		#RHDebug::show('id', $nsInfo->id);
		$nsInfo->parent = ($fieldCount > 2 && strlen($fields[2]) > 0)
			? $fields[2]
			: $nsInfo->base;
		$nsInfo->name = ($fieldCount > 3 && strlen($fields[3]) > 0)
			? $fields[3]
			: $nsInfo->base;
		$nsInfo->mainPage = ($fieldCount > 4 && strlen($fields[4]) > 0)
			? $fields[4]
			: $nsInfo->buildMainPage($nsInfo->name);
		$nsInfo->category = ($fieldCount > 5 && strlen($fields[5]) > 0)
			? $fields[5]
			: $nsInfo->base;
		$nsInfo->trail = ($fieldCount > 6 && strlen($fields[6]) > 0)
			? $fields[6]
			: $nsInfo->buildTrail($nsInfo->mainPage, $nsInfo->name);
		$nsInfo->gameSpace = $nsInfo->nsId > 100 && $nsInfo->nsId < 400 && $nsInfo->nsId !== 200;

		return $nsInfo;
	}

	public static function fromNamespace(int $nsId, ?string $pageName = ''): NSInfoNamespace
	{
		global $wgContLang;

		$nsId = MWNamespace::getSubject($nsId);
		$nsInfo = new NSInfoNamespace($nsId, $pageName);
		$nsInfo->base = $wgContLang->getFormattedNsText($nsId);
		$nsInfo->category = $nsInfo->base;
		$nsInfo->gameSpace = $nsInfo->nsId > 100 && $nsInfo->nsId < 400 && $nsInfo->nsId !== 200;
		$nsInfo->id = $nsInfo->getDefaultId();
		$nsInfo->name = $nsInfo->base;
		$nsInfo->parent = $nsInfo->base;
		$nsInfo->mainPage = $nsInfo->buildMainPage($nsInfo->name);
		$nsInfo->trail = $nsInfo->buildTrail($nsInfo->mainPage, $nsInfo->name);

		return $nsInfo;
	}

	public static function fromRow(array $row): NSInfoNamespace
	{
		$nsInfo = new NSInfoNamespace((int)$row[NSInfoSql::FIELD_INDEX], $row[NSInfoSql::FIELD_PAGENAME]);
		$nsInfo->base = $row[NSInfoSql::FIELD_BASE];
		$nsInfo->category = $row[NSInfoSql::FIELD_CATEGORY];
		$nsInfo->gameSpace = $row[NSInfoSql::FIELD_GAMESPACE];
		$nsInfo->id = $row[NSInfoSql::FIELD_ID];
		$nsInfo->mainPage = $row[NSInfoSql::FIELD_MAIN_PAGE];
		$nsInfo->name = $row[NSInfoSql::FIELD_NAME];
		$nsInfo->parent = $row[NSInfoSql::FIELD_PARENT];
		$nsInfo->trail = $row[NSInfoSql::FIELD_TRAIL];

		return $nsInfo;
	}
	#endregion

	#region Public Functions
	public function addSubSpace(NSInfoNamespace $subSpace)
	{
		if ($subSpace->nsId !== $this->nsId) {
			throw new Exception('Invalid subspace for current namespace.');
		}

		$this->subSpaces[$subSpace->pageName] = $subSpace;
	}

	public function buildMainPage(string $name): string
	{
		return $this->getFull() . $name;
	}

	public function buildTrail(string $mainPage, string $name): string
	{
		return "[[$mainPage|$name]]";
	}

	public function getBase(): string
	{
		return $this->base;
	}

	public function getCategory(): string
	{
		return $this->category;
	}

	public function getDefaultId(): string
	{
		return strtoupper($this->base);
	}

	public function getFull(): string
	{
		return $this->base . (strlen($this->pageName) ? '/' : ':');
	}

	public function getGameSpace(): bool
	{
		return $this->gameSpace;
	}

	public function getId(): string
	{
		return $this->id;
	}

	public function getMainPage(): string
	{
		return $this->mainPage;
	}

	public function getModName(): string
	{
		return end(explode('/', $this->pageName));
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getNsId(bool $original = false): int
	{
		return $original
			? $this->originalNsId
			: $this->nsId;
	}

	public function getParent(): string
	{
		return $this->parent;
	}

	public function getPageName(bool $original = false): string
	{
		return ($original ? $this->originalPageName : $this->pageName) ?? '';
	}

	/**
	 * Gets any subspaces in this nsId.
	 *
	 * @return NSInfoNamespace[]
	 */
	public function getSubSpaces(): array
	{
		return $this->subSpaces;
	}

	public function getTracking(): Title
	{
		return $this->tracking;
	}

	public function getTrail(): string
	{
		return $this->trail;
	}

	public function savePage(User $user): void
	{
		$gameSpace = $this->gameSpace ? 'Yes' : 'No';
		$text =
			"This page collects all [[Special:WhatLinksHere/{{FULLPAGENAME}}|back links]] coming from the [[MediaWiki:Namespace Info|Namespace Info]] parser functions for the namespace listed below. Note that the {{Pl|{{FULLPAGENAME}}|edit history|action=history}} is purely for tracking changes made using [[Special:NSInfo|the editor]]. It's entirely system maintained and editing this page will have no effect on anything.\n\n" .
			"===Namespace Info===\n" .
			"'''NS_NAME''': {$this->name}<br>\n" .
			"'''NS_ID''': {$this->id}<br>\n" .
			"'''NS_BASE''': {$this->base}<br>\n" .
			"'''NS_CATEGORY''': {$this->category}<br>\n" .
			"'''NS_MAINPAGE''': [[{$this->mainPage}]]<br>\n" .
			"'''NS_PARENT''': {$this->parent}<br>\n" .
			"'''NS_TRAIL''': {$this->trail}<br>\n" .
			"'''GAMESPACE''': {$gameSpace}";

		$content = new WikitextContent($text);
		$page = WikiPage::factory($this->tracking);
		$page->doEditContent(
			$content,
			'Namespace updated',
			EDIT_SUPPRESS_RC | EDIT_INTERNAL,
			false,
			$user
		);
	}
	#endregion
}
