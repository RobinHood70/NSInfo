<?php

class NSInfoNamespace
{
	#region Private Static Fields
	/** @var NSInfoNamespace $empty */
	private static $empty;
	#endregion

	#region Private Fields
	/** @var string $base The full text name of the (pseudo-)namespace without a trailing colon or slash. */
	private $base = '';

	/** @var string $category The text to be used in category names. */
	private $category = '';

	/** @var bool $gamespace Whether the (pseudo-)namespace counts as being in game space. */
	private $gamespace = true;

	/** @var string $id The shortened text ID of the (pseudo-)namespace. */
	private $id = '';

	/** @var string $mainPage The main page for the (pseudo-)namespace. */
	private $mainPage = '';

	/** @var string $name The friendly name of the (pseudo-)namespace. */
	private $name = '';

	/** @var int|bool $nsId The numerical MediaWiki ID of the namespace or false if the requested namespace was invalid. */
	private $nsId;

	/** @var string $pageName The pagename of the pseudo-namespace; otherwise an empty string. */
	private $pageName;

	/** @var string $parent The parent namespace if this is a child (pseudo-)namespace. */
	private $parent = '';

	/** @var NSInfoNamespace[] $pseudoSpaces Any pseudo-namespaces belonging in a given root namespace. */
	private $pseudoSpaces = [];

	/** @var string $trail The trail to use for pages in this (pseudo-)namespace. */
	private $trail = '';
	#endregion

	#region Constructor
	public function __construct($nsOrBase, $pageName = '')
	{
		$contLang = VersionHelper::getInstance()->getContentLanguage();

		if (is_int($nsOrBase)) {
			$this->pageName = $pageName ?? '';
			$nsName = $contLang->getNsText($nsOrBase);
			if ($nsName) {
				$this->nsId = $nsOrBase;
				$this->base = strlen($this->pageName)
					? "{$nsName}:{$this->pageName}"
					: $nsName;
			} else {
				$this->nsId = false;
			}
		} else if ($nsOrBase === false) {
			$this->nsId = false;
			$this->pageName = '';
			$this->base = '';
		} else {
			$exploded = explode(':', $nsOrBase, 2);
			$nsId = VersionHelper::getInstance()->getContentLanguage()->getNsIndex(strtr($exploded[0], ' ', '_'));
			$this->nsId = $nsId;
			$this->pageName = count($exploded) > 1 ? $exploded[1] : '';
			$this->base = $nsOrBase;
		}
	}
	#endregion

	#region Public Static Functions
	public static function empty()
	{
		if (!isset(self::$empty)) {
			$nsInfo = new NSInfoNamespace(false, '');
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
	 * Returns default namespace information from a namespace ID.
	 *
	 * @param int $nsId
	 * @param string|null $pageName
	 *
	 * @return NSInfoNamespace
	 *
	 */
	public static function fromNamespace(int $nsId, ?string $pageName = ''): NSInfoNamespace
	{
		$nsId = MWNamespace::getSubject($nsId);
		$nsInfo = new NSInfoNamespace($nsId, $pageName);
		if ($nsInfo->getNsId() === false) {
			return NSInfoNamespace::empty();
		}

		$nsInfo->category = $nsInfo->base;
		$nsInfo->gamespace = $nsInfo->getDefaultGamespace();
		$nsInfo->id = $nsInfo->getDefaultId();
		$nsInfo->name = $nsInfo->base;
		$nsInfo->parent = $nsInfo->base;
		$nsInfo->mainPage = $nsInfo->buildMainPage($nsInfo->name);
		$nsInfo->trail = $nsInfo->buildTrail($nsInfo->mainPage, $nsInfo->name);
		return $nsInfo;
	}

	public static function fromRow(string $row): ?NSInfoNamespace
	{
		if (($row[0] ?? '') !== '|') {
			return null;
		}

		$row = substr($row, 1);
		$row = str_replace('||', '\n|', $row);
		$fields = explode('\n|', $row);
		$fields = array_map('trim', $fields);
		$fields = array_pad($fields, 8, '');

		$nsInfo = new NSInfoNamespace($fields[0]);
		if ($nsInfo->getNsId() === false) {
			return null;
		}

		$nsInfo->id = strlen($fields[1])
			? $fields[1]
			: $nsInfo->getDefaultId();
		$nsInfo->parent = strlen($fields[2])
			? $fields[2]
			: $nsInfo->base;
		$nsInfo->name = strlen($fields[3])
			? $fields[3]
			: $nsInfo->base;
		$nsInfo->mainPage = strlen($fields[4])
			? $fields[4]
			: $nsInfo->buildMainPage($nsInfo->name);
		$nsInfo->category = strlen($fields[5])
			? $fields[5]
			: $nsInfo->base;
		$nsInfo->trail = strlen($fields[6])
			? $fields[6]
			: $nsInfo->buildTrail($nsInfo->mainPage, $nsInfo->name);
		$nsInfo->gamespace = strlen($fields[7])
			? filter_var($fields[7], FILTER_VALIDATE_BOOLEAN)
			: $nsInfo->getDefaultGamespace();

		return $nsInfo;
	}

	public function getDefaultGamespace()
	{
		// The IDs listed are the preferred custom namespace ranges for all wikis and are not UESP-specific. The idea here is to make the "No" namespaces explicit in the table.
		$id = $this->nsId;
		return ($id >= 100 && $id < 200) ||
			($id >= 3000 && $id < 5000);
	}
	#endregion

	#region Public Functions
	public function addPseudoSpaces(array $pseudoSpaces)
	{
		usort($pseudoSpaces, function ($a, $b) {
			return mb_strlen($b->base) <=> mb_strlen($a->base);
		});

		$this->pseudoSpaces = $pseudoSpaces;
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
		return $this->gamespace;
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

	/**
	 * @return int|bool
	 */
	public function getNsId()
	{
		return $this->nsId;
	}

	public function getParent(): string
	{
		return $this->parent;
	}

	public function getPageName(): string
	{
		return $this->pageName;
	}

	/**
	 * Gets any pseudo-namespaces in this namespace.
	 *
	 * @return NSInfoNamespace[]
	 */
	public function getPseudoSpaces(): array
	{
		return $this->pseudoSpaces;
	}

	public function getTrail(): string
	{
		return $this->trail;
	}
	#endregion
}
