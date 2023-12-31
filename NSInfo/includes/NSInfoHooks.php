<?php

/** @todo Add {{#define/local/preview:a=b|c=d}} */
/** @todo
 * This is essentially four extensions in one and if desired in the future, could be fairly readily split for more of a
 * single-purpse feel to each extension. Catpagetemplate would need a bit of work, as it's is a bit too coupled with
 * the data features right now, but a few hooks would probably take care of that.
 */
class NSInfoHooks
{
	#region Public Static Functions
	/**
	 * Migrates the old MetaTemplate tables to new ones. The basic functionality is the same, but names and indeces
	 * have been altered and the datestamp removed.
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates(DatabaseUpdater $updater): void
	{
		NSInfoSql::getInstance()->onLoadExtensionSchemaUpdates($updater);
	}

	/**
	 * Enables NSInfo's variables.
	 *
	 * @param array $aCustomVariableIds The list of custom variables to add to.
	 */
	public static function onMagicWordwgVariableIDs(array &$aCustomVariableIds): void
	{
		$aCustomVariableIds[] = NSInfo::PF_GAMESPACE;
		$aCustomVariableIds[] = NSInfo::PF_MOD_NAME;
		$aCustomVariableIds[] = NSInfo::PF_NS_BASE;
		$aCustomVariableIds[] = NSInfo::PF_NS_CATEGORY;
		$aCustomVariableIds[] = NSInfo::PF_NS_FULL;
		$aCustomVariableIds[] = NSInfo::PF_NS_ID;
		$aCustomVariableIds[] = NSInfo::PF_NS_MAINPAGE;
		$aCustomVariableIds[] = NSInfo::PF_NS_NAME;
		$aCustomVariableIds[] = NSInfo::PF_NS_PARENT;
		$aCustomVariableIds[] = NSInfo::PF_NS_TRAIL;
	}

	/**
	 * Initialize parser functions followed by NSInfo general initialization.
	 *
	 * @param Parser $parser The parser in use.
	 */
	public static function onParserFirstCallInit(Parser $parser): void
	{
		$parser->setFunctionHook(NSInfo::PF_GAMESPACE, 'NSInfo::doGameSpace', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_MOD_NAME, 'NSInfo::doModName', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_NS_BASE, 'NSInfo::doNsBase', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_NS_CATEGORY, 'NSInfo::doNsCategory', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_NS_CATLINK, 'NSInfo::doNsCatlink', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_NS_FULL, 'NSInfo::doNsFull', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_NS_ID, 'NSInfo::doNsId', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_NS_MAINPAGE, 'NSInfo::doNsMainPage', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_NS_NAME, 'NSInfo::doNsName', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_NS_PARENT, 'NSInfo::doNsParent', SFH_OBJECT_ARGS | SFH_NO_HASH);
		$parser->setFunctionHook(NSInfo::PF_NS_TRAIL, 'NSInfo::doNsTrail', SFH_OBJECT_ARGS | SFH_NO_HASH);
	}

	/**
	 * Gets the value of the specified variable.
	 *
	 * @param Parser $parser The parser in use.
	 * @param array $variableCache The variable cache. Can be used to store values for faster evaluation in subsequent calls.
	 * @param mixed $magicWordId The magic word ID to evaluate.
	 * @param mixed $ret The return value.
	 * @param PPFrame $frame The frame in use.
	 *
	 * @return bool Always true
	 */
	public static function onParserGetVariableValueSwitch(Parser $parser, array &$variableCache, $magicWordId, &$ret, PPFrame $frame): bool
	{
		switch ($magicWordId) {
			case NSInfo::PF_GAMESPACE:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getGameSpace();
					$variableCache[$magicWordId] = $ret;
				}

				break;
			case NSInfo::PF_MOD_NAME:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getModName();
					$variableCache[$magicWordId] = $ret;
				}

				break;
			case NSInfo::PF_NS_BASE:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getBase();
					$variableCache[$magicWordId] = $ret;
				}

				break;
			case NSInfo::PF_NS_CATEGORY:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getCategory();
					$variableCache[$magicWordId] = $ret;
				}

				break;
			case NSInfo::PF_NS_FULL:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getFull();
					$variableCache[$magicWordId] = $ret;
				}

				break;
			case NSInfo::PF_NS_ID:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getId();
					$variableCache[$magicWordId] = $ret;
				}

				break;
			case NSInfo::PF_NS_MAINPAGE:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getMainPage();
					$variableCache[$magicWordId] = $ret;
				}

				break;
			case NSInfo::PF_NS_NAME:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getName();
					$variableCache[$magicWordId] = $ret;
				}

				break;
			case NSInfo::PF_NS_PARENT:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getParent();
					$variableCache[$magicWordId] = $ret;
				}

				break;
			case NSInfo::PF_NS_TRAIL:
				$ns = NSInfo::getNsInfo($parser, $frame);
				if ($ns) {
					$ret = $ns->getTrail();
					$variableCache[$magicWordId] = $ret;
				}

				break;
		}

		return true;
	}
	#endregion
}
