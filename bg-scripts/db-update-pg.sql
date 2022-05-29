ALTER TABLE config ADD COLUMN twofa_type integer DEFAULT '0';
ALTER TABLE config ADD COLUMN twofa_duo_api_hostname varchar(64);
ALTER TABLE config ADD COLUMN twofa_duo_integration_key varchar(64);
ALTER TABLE config ADD COLUMN twofa_duo_secret_key varchar(64);
ALTER TABLE config ADD COLUMN twofa_duo_a_key varchar(64);

ALTER TABLE users ADD COLUMN ggl_secret varchar(100) DEFAULT '' NOT NULL;
ALTER TABLE users ADD COLUMN ggl_enrolled integer DEFAULT '0' NOT NULL;

CREATE TABLE adusrgrp (
        adusrgrpid             bigint                        NOT NULL,
        name                   varchar(64)     DEFAULT ''    NOT NULL,
        roleid                 bigint                        NOT NULL,
        PRIMARY KEY (adusrgrpid)
);
CREATE UNIQUE INDEX adusrgrp_1 ON adusrgrp (name);
ALTER TABLE adusrgrp ADD CONSTRAINT c_adusrgrp_1 FOREIGN KEY (roleid) REFERENCES role (roleid) ON DELETE CASCADE;

CREATE TABLE adgroups_groups (
        id                     bigint                        NOT NULL,
        usrgrpid               bigint                        NOT NULL,
        adusrgrpid             bigint                        NOT NULL,
        PRIMARY KEY (id)
);
CREATE UNIQUE INDEX adgroups_groups_1 ON adgroups_groups (usrgrpid,adusrgrpid);
CREATE INDEX adgroups_groups_2 ON adgroups_groups (adusrgrpid);
ALTER TABLE adgroups_groups ADD CONSTRAINT c_adgroups_groups_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE;
ALTER TABLE adgroups_groups ADD CONSTRAINT c_adgroups_groups_2 FOREIGN KEY (adusrgrpid) REFERENCES adusrgrp (adusrgrpid) ON DELETE CASCADE;
