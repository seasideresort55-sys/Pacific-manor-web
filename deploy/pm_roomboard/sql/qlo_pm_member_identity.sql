-- 太平洋莊園｜qlo_pm_member 身分欄位（可重複執行的意圖；正式請跑 pm_member_identity_install.php）
-- 主鍵維持既有 id_member（= user_id）。Email 不可當 UNIQUE 身分。
-- 此檔為對照用；線上請優先用 PHP 安裝程式偵測欄位／索引後再 ALTER。

-- 1) 身分欄位（允許 NULL；空字串請先改成 NULL 再加 UNIQUE）
ALTER TABLE `qlo_pm_member`
  ADD COLUMN `apple_sub` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Sign in with Apple sub',
  ADD COLUMN `google_sub` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Google OIDC sub';

-- phone 多半已存在；若沒有：
-- ALTER TABLE `qlo_pm_member` ADD COLUMN `phone` VARCHAR(20) NULL DEFAULT NULL;

-- 2) 空字串改 NULL，避免 UNIQUE 把多筆 '' 當重複
UPDATE `qlo_pm_member` SET `apple_sub` = NULL WHERE `apple_sub` IS NOT NULL AND TRIM(`apple_sub`) = '';
UPDATE `qlo_pm_member` SET `google_sub` = NULL WHERE `google_sub` IS NOT NULL AND TRIM(`google_sub`) = '';
UPDATE `qlo_pm_member` SET `phone` = NULL WHERE `phone` IS NOT NULL AND TRIM(`phone`) = '';

-- 3) UNIQUE NULL（MySQL/MariaDB 允許多列 NULL）
ALTER TABLE `qlo_pm_member` ADD UNIQUE KEY `uniq_pm_member_apple_sub` (`apple_sub`);
ALTER TABLE `qlo_pm_member` ADD UNIQUE KEY `uniq_pm_member_google_sub` (`google_sub`);
ALTER TABLE `qlo_pm_member` ADD UNIQUE KEY `uniq_pm_member_phone` (`phone`);

-- 4) Email 只當輔助聯絡：拿掉 UNIQUE，改普通 INDEX（不要 DROP 欄位）
-- 規格禁止「Unique email migration required」。實際索引名請用安裝程式偵測。
-- ALTER TABLE `qlo_pm_member` DROP INDEX `email`;
-- ALTER TABLE `qlo_pm_member` ADD INDEX `idx_pm_member_email` (`email`);
-- ALTER TABLE `qlo_pm_member` MODIFY `email` VARCHAR(190) NULL DEFAULT NULL;

-- 5) 線上既有對照表（可與欄位並存）：UNIQUE(provider, subject) → id_member（= user_id）
CREATE TABLE IF NOT EXISTS `qlo_pm_social_identity` (
  `id_social_identity` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_member` INT UNSIGNED NOT NULL COMMENT 'user_id',
  `provider` VARCHAR(32) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `email` VARCHAR(190) NULL,
  `date_add` DATETIME NOT NULL,
  PRIMARY KEY (`id_social_identity`),
  UNIQUE KEY `uniq_pm_social_provider_subject` (`provider`,`subject`),
  KEY `idx_pm_social_member` (`id_member`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
