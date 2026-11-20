# Tables are created only if missing: activating the module again must never drop the consent proofs.

# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- consent_category
-- ---------------------------------------------------------------------


CREATE TABLE IF NOT EXISTS `consent_category`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL,
    `required` TINYINT DEFAULT 0 NOT NULL,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `consent_category_code_unique` (`code`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- consent_service
-- ---------------------------------------------------------------------


CREATE TABLE IF NOT EXISTS `consent_service`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL,
    `category_id` INTEGER NOT NULL,
    `custom` TINYINT DEFAULT 0 NOT NULL,
    `provider` VARCHAR(255),
    `cookies` LONGTEXT,
    `privacy_url` VARCHAR(255),
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `consent_service_code_unique` (`code`),
    INDEX `fi_consent_service_category_id` (`category_id`),
    CONSTRAINT `fk_consent_service_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `consent_category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- consent_policy
-- ---------------------------------------------------------------------


CREATE TABLE IF NOT EXISTS `consent_policy`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `snapshot_hash` CHAR(64) NOT NULL,
    `snapshot` LONGTEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `consent_policy_snapshot_hash_unique` (`snapshot_hash`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- consent_record
-- ---------------------------------------------------------------------


CREATE TABLE IF NOT EXISTS `consent_record`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `visitor_id` CHAR(36) NOT NULL,
    `policy_id` INTEGER NOT NULL,
    `accepted` LONGTEXT NOT NULL,
    `refused` LONGTEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_consent_record_visitor_id` (`visitor_id`),
    INDEX `idx_consent_record_created_at` (`created_at`),
    INDEX `fi_consent_record_policy_id` (`policy_id`),
    CONSTRAINT `fk_consent_record_policy_id`
        FOREIGN KEY (`policy_id`)
        REFERENCES `consent_policy` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- consent_category_i18n
-- ---------------------------------------------------------------------


CREATE TABLE IF NOT EXISTS `consent_category_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `consent_category_i18n_fk_146d94`
        FOREIGN KEY (`id`)
        REFERENCES `consent_category` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- consent_service_i18n
-- ---------------------------------------------------------------------


CREATE TABLE IF NOT EXISTS `consent_service_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `consent_service_i18n_fk_6931ec`
        FOREIGN KEY (`id`)
        REFERENCES `consent_service` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
