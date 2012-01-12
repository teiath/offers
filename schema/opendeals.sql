SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';

DROP SCHEMA IF EXISTS `opendeals` ;
CREATE SCHEMA IF NOT EXISTS `opendeals` DEFAULT CHARACTER SET utf8 ;
USE `opendeals` ;

-- -----------------------------------------------------
-- Table `opendeals`.`offer_categories`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `opendeals`.`offer_categories` ;

CREATE  TABLE IF NOT EXISTS `opendeals`.`offer_categories` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `name` MEDIUMTEXT NOT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `opendeals`.`offer_types`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `opendeals`.`offer_types` ;

CREATE  TABLE IF NOT EXISTS `opendeals`.`offer_types` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `name` MEDIUMTEXT NOT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `opendeals`.`users`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `opendeals`.`users` ;

CREATE  TABLE IF NOT EXISTS `opendeals`.`users` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `username` MEDIUMTEXT NOT NULL ,
  `password` MEDIUMTEXT NOT NULL ,
  `email` MEDIUMTEXT NOT NULL ,
  `is_banned` TINYINT(1)  NOT NULL DEFAULT FALSE ,
  `role` MEDIUMTEXT NOT NULL ,
  PRIMARY KEY (`id`) )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `opendeals`.`companies`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `opendeals`.`companies` ;

CREATE  TABLE IF NOT EXISTS `opendeals`.`companies` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `name` MEDIUMTEXT NOT NULL ,
  `logo` BLOB NOT NULL ,
  `address` MEDIUMTEXT NOT NULL ,
  `postalcode` VARCHAR(5) NULL DEFAULT NULL ,
  `phone` VARCHAR(10) NOT NULL ,
  `fax` VARCHAR(10) NULL DEFAULT NULL ,
  `service_type` MEDIUMTEXT NOT NULL ,
  `afm` VARCHAR(9) NOT NULL ,
  `doy` MEDIUMTEXT NULL ,
  `working_hours` MEDIUMTEXT NULL DEFAULT NULL ,
  `longitude` DOUBLE NULL DEFAULT NULL ,
  `latitude` DOUBLE NULL DEFAULT NULL ,
  `is_enabled` TINYINT(1)  NOT NULL DEFAULT FALSE ,
  `user_id` INT NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `fk_companies_users1` (`user_id` ASC) ,
  CONSTRAINT `fk_companies_users1`
    FOREIGN KEY (`user_id` )
    REFERENCES `opendeals`.`users` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `opendeals`.`offers`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `opendeals`.`offers` ;

CREATE  TABLE IF NOT EXISTS `opendeals`.`offers` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `description` TEXT NULL DEFAULT NULL ,
  `starting` DATETIME NOT NULL ,
  `ending` DATETIME NULL DEFAULT NULL ,
  `expiration_date` DATETIME NULL DEFAULT NULL ,
  `is_active` TINYINT(1)  NOT NULL DEFAULT FALSE ,
  `total_quantity` INT NOT NULL DEFAULT 0 ,
  `current_quantity` INT NOT NULL DEFAULT 0 ,
  `tags` MEDIUMTEXT NULL ,
  `is_draft` TINYINT(1)  NOT NULL DEFAULT TRUE ,
  `photo` BLOB NULL ,
  `offer_category_id` INT NOT NULL ,
  `offer_type_id` INT NOT NULL ,
  `company_id` INT NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `fk_offers_offer_categories` (`offer_category_id` ASC) ,
  INDEX `fk_offers_offer_types1` (`offer_type_id` ASC) ,
  INDEX `fk_offers_companies1` (`company_id` ASC) ,
  CONSTRAINT `fk_offers_offer_categories`
    FOREIGN KEY (`offer_category_id` )
    REFERENCES `opendeals`.`offer_categories` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_offers_offer_types1`
    FOREIGN KEY (`offer_type_id` )
    REFERENCES `opendeals`.`offer_types` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_offers_companies1`
    FOREIGN KEY (`company_id` )
    REFERENCES `opendeals`.`companies` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `opendeals`.`students`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `opendeals`.`students` ;

CREATE  TABLE IF NOT EXISTS `opendeals`.`students` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `firstname` MEDIUMTEXT NOT NULL ,
  `lastname` MEDIUMTEXT NOT NULL ,
  `receive_email` TINYINT(1)  NOT NULL DEFAULT FALSE ,
  `token` MEDIUMTEXT NOT NULL ,
  `avatar` BLOB NULL ,
  `user_id` INT NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `fk_students_users1` (`user_id` ASC) ,
  CONSTRAINT `fk_students_users1`
    FOREIGN KEY (`user_id` )
    REFERENCES `opendeals`.`users` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;


-- -----------------------------------------------------
-- Table `opendeals`.`coupons`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `opendeals`.`coupons` ;

CREATE  TABLE IF NOT EXISTS `opendeals`.`coupons` (
  `id` INT NOT NULL AUTO_INCREMENT ,
  `serial_number` TEXT NOT NULL ,
  `created` DATETIME NOT NULL ,
  `is_used` TINYINT(1)  NOT NULL DEFAULT 0 ,
  `offer_id` INT NOT NULL ,
  `student_id` INT NOT NULL ,
  PRIMARY KEY (`id`) ,
  INDEX `fk_coupons_offers1` (`offer_id` ASC) ,
  INDEX `fk_coupons_students1` (`student_id` ASC) ,
  CONSTRAINT `fk_coupons_offers1`
    FOREIGN KEY (`offer_id` )
    REFERENCES `opendeals`.`offers` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_coupons_students1`
    FOREIGN KEY (`student_id` )
    REFERENCES `opendeals`.`students` (`id` )
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8;



SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;

