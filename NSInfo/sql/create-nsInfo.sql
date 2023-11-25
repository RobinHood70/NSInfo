
-- SQL to create the nsInfo table for NSInfo.

-- This is designed to be run from the install script or via update.php, ensuring that proper variable substitution is
-- done.

-- There should be one entry per namespace or pseudo-namespace in this table. Entries can be omitted if a namespace
-- uses all default values. Default values are listed in the comments beside each field definition. "nsBase" in the
-- comments refers to the combination of the canonical name for nsNamespace and, if non-blank, nsPath preceded by a
-- colon (i.e., "Skyrim" or "Skyrim Mod:The Forgotten City").

CREATE TABLE IF NOT EXISTS /*_*/nsInfo (
    nsIndex INT(11) NOT NULL,
    nsPageName VARCHAR(255) NOT NULL DEFAULT '',
    nsBase VARCHAR(255) NOT NULL DEFAULT '',
    nsCategory VARCHAR(255) NOT NULL DEFAULT '', -- Internal default: nsBase
    nsGamespace TINYINT(1) DEFAULT 1,            -- Internal default: 0 if index < 100; otherwise 1
	nsId VARCHAR(255) NOT NULL DEFAULT '',       -- Internal default: upper-case of nsBase
    nsMainPage VARCHAR(255) NOT NULL DEFAULT '', -- Internal default: empty (this is a change from UespCustomCode)
	nsName VARCHAR(255) NOT NULL DEFAULT '',     -- Internal default: nsBase
	nsParent VARCHAR(255) NOT NULL DEFAULT '',   -- Internal default: nsBase
    nsTrail VARCHAR(500) NOT NULL DEFAULT '',    -- Internal default: [[nsMainPage|nsName]]
	PRIMARY KEY (nsIndex, nsPageName)
) /*$wgDBTableOptions*/;