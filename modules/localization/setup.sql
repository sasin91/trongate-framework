SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

CREATE TABLE IF NOT EXISTS `trongate_localization`
(
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `locale`     varchar(65) DEFAULT NULL,
    `key`        varchar(60) DEFAULT NULL,
    `value`      LONGTEXT    DEFAULT NULL,
    `created_at` TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4;

INSERT INTO `trongate_localization` (`id`, `locale`, `key`, `value`)
VALUES (1, 'da_DK', 'Hello', 'Haløjsa Trongate'),
       (2, 'en_US', 'Hello', 'Hello Trongate'),
       (3, 'da_DK', 'Manage Localizations', 'Administrer oversættelser'),
       (4, 'en_US', 'Manage Localizations', 'Manage Localizations');
COMMIT;