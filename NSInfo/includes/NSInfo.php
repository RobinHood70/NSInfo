<?php

/**
 * An extension to add data persistence and variable manipulation to MediaWiki.
 *
 * At this point, the code could easily be split into four separate extensions based on the SSTNG constants, but at as
 * they're likely to all be used together, with the possible exception of the Define group as of MW 1.35, it seems
 * easier to keep them together for easier maintenance.
 */
class NSInfo
{
	#region Public Constants
	public const KEY_NSINFO = '@nsinfo';

	public const NA_NS_BASE = 'ns_base';
	public const NA_NS_ID = 'ns_id';

	public const PF_GAMESPACE = 'GAMESPACE';
	public const PF_MOD_NAME = 'MOD_NAME';
	public const PF_NS_BASE = 'NS_BASE';
	public const PF_NS_CATEGORY = 'NS_CATEGORY';
	public const PF_NS_CATLINK = 'NS_CATLINK';
	public const PF_NS_FULL = 'NS_FULL';
	public const PF_NS_ID = 'NS_ID';
	public const PF_NS_MAINPAGE = 'NS_MAINPAGE';
	public const PF_NS_NAME = 'NS_NAME';
	public const PF_NS_PARENT = 'NS_PARENT';
	public const PF_NS_TRAIL = 'NS_TRAIL';
	#endregion

	#region Private Static Variables
	/** @var $cache string[] */
	private static $cache = [];

	/** @var ?NSInfoNamespace[] $info */
	private static $info = null;
	#endregion

	#region Public Static Functions
	public static function doGameSpace(Parser $parser, PPFrame $frame, array $args): bool
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getGameSpace();
	}

	public static function doModName(Parser $parser, PPFrame $frame, array $args): string
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getModName();
	}

	public static function doNsBase(Parser $parser, PPFrame $frame, array $args): string
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getBase();
	}

	public static function doNsCategory(Parser $parser, PPFrame $frame, array $args): string
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getCategory();
	}

	public static function doNsCatlink(Parser $parser, PPFrame $frame, array $args): string
	{
		global $wgContLang;
		$page = trim($frame->expand($args[0]));
		if ($page === '') {
			return ParserHelper::error('nsinfo-nslink-emptypagename');
		}

		$catspace = $wgContLang->getNsText(NS_CATEGORY);
		$ns = self::getNsInfo($parser, $frame);
		$prefix = $ns->getCategory();
		$sortkey = count($args) > 1
			? '|' . trim($frame->expand($args[1]))
			: '';

		return "[[$catspace:$prefix-$page$sortkey]]";
	}

	public static function doNsFull(Parser $parser, PPFrame $frame, array $args): string
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getFull();
	}

	public static function doNsId(Parser $parser, PPFrame $frame, array $args): string
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getId();
	}

	public static function doNsMainPage(Parser $parser, PPFrame $frame, array $args): string
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getMainPage();
	}

	public static function doNsName(Parser $parser, PPFrame $frame, array $args): string
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getName();
	}

	public static function doNsParent(Parser $parser, PPFrame $frame, array $args): string
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getParent();
	}

	public static function doNsTrail(Parser $parser, PPFrame $frame, array $args): string
	{
		$ns = self::getNsInfo($parser, $frame, $args);
		return $ns->getTrail();
	}

	/**
	 * Gets namespace information for the associated namespace or subspace. Namespaces not found in the table will be
	 * set to default values.
	 *
	 * @param \Parser $parser The parser in use.
	 * @param \PPFrame $frame The frame in use.
	 *
	 * @return NSInfoNamespace
	 */
	public static function getNsInfo(Parser $parser, PPFrame $frame, array $args = null): NSInfoNamespace
	{
		if (is_null(self::$info)) {
			self::$info = NSInfoSql::getInstance()->getNamespaceInfo();
		}

		/** @var Title $title */
		/** @var PPTemplateFrame_Hash $frame */
		if (is_null($args)) {
			$arg = $frame->getNamedArgument(self::NA_NS_BASE);
			if ($arg === false) {
				$arg = $frame->getNamedArgument(self::NA_NS_ID);
			}
		} else {
			$arg = trim($frame->expand($args[0]));
		}

		if ($arg === false) {
			// We have no arguments or magic variables, so pull the info from the parser's Title object.
			$title = $parser->getTitle();
			if (!$title) {
				// RHDebug::writeFile(RHDebug::getStacktrace());
				// RHDebug::writeFile('Parser didn\'t have title, trying frame.');
				// In the rare event parser doesn't return a title, try the frame, since that's a required parameter in
				// the call chain to get here.
				while ($frame->parent) {
					$frame = $frame->parent;
				}

				$title = $frame->getTitle();
				if (!$title) {
					// RHDebug::writeFile('Frame didn\'t have title either.');
				}
			}
		} else {
			$ns = self::nsFromArg($arg);
			if ($ns !== false) {
				return $ns;
			}

			// If none of the above, assume it's a Title.
			$title = Title::newFromText($arg);
		}

		if (!$title) {
			// If we somehow failed to get a title, abort.
			return NSInfoNamespace::empty();
		}

		$articleId = $title->getArticleId();
		if ($articleId > 0 && isset(self::$cache[$articleId])) {
			return self::$cache[$articleId];
		}

		$index = $title->getNamespace();
		if ($index > 0) {
			$index &= ~1;
		}

		// It's a title, so check the namespace and pagename and see if we can find a match.
		if (isset(self::$info[$index])) {
			$ns = self::$info[$index];
		} else {
			$ns = NSInfoNamespace::fromNamespace($index);
			self::$info[$index] = $ns;
		}

		/** @var NSInfoNamespace $ns */
		$subSpaces = $ns->getSubSpaces();
		if (count($subSpaces)) {
			$longest = 0;
			// Append slash to pagename and subspace so NS:ModSomething doesn't match NS:Mod.
			$pageName = $title->getText() . '/';
			foreach ($subSpaces as $subSpace) {
				$subSpaceName = $subSpace->getPageName() . '/';
				$spaceLen = strlen($subSpaceName);
				if ($spaceLen > $longest && strncmp($pageName, $subSpaceName, $spaceLen) === 0) {
					$ns = $subSpace;
				}
			}
		}

		if ($articleId > 0) {
			self::$cache[$articleId] = $ns;
		}

		$link = $ns->getTracking();
		$parser->getOutput()->addTemplate($link, $link->getArticleID(), $link->getLatestRevID());
		return $ns;
	}

	/**
	 * Gets an NSInfoNamespace from the argument provided. Returns false if the argument doesn't correspond to a
	 * recognized namespace or pseudo-namespace (e.g., a page name).
	 *
	 * @param string $arg The argument to check.
	 *
	 * @return NSInfoNamespace|false
	 *
	 */
	public static function nsFromArg(string $arg)
	{
		global $wgContLang;

		// Quick check: is it a recognized ns_base/ns_id/namespace index?
		if (isset(self::$info[strtolower($arg)])) {
			return self::$info[strtolower($arg)];
		}

		// Is it an unrecognized namespace name or index?
		$index = is_numeric($arg) ? (int)$arg : $wgContLang->getNsIndex($arg);
		if ($index !== false) {
			if ($index > 0) {
				$index &= ~1;
			}

			// Is it the talk space of a recognized namespace?
			if (isset(self::$info[$index])) {
				return self::$info[$index];
			}

			// It's an unrecognized namespace.
			$ns = NSInfoNamespace::fromNamespace($index);
			self::$info[$index] = $ns;
			return $ns;
		}

		return false;
	}
	#endregion
}
