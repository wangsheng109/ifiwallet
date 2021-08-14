-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2021-08-14 18:12:22
-- 服务器版本： 5.7.35
-- PHP 版本： 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `ette`
--

-- --------------------------------------------------------

--
-- 表的结构 `blocks`
--

CREATE TABLE `blocks` (
  `hash` char(66) NOT NULL,
  `number` bigint(20) NOT NULL,
  `time` bigint(20) NOT NULL,
  `parenthash` char(66) NOT NULL,
  `difficulty` varchar(255) NOT NULL,
  `gasused` bigint(20) NOT NULL,
  `gaslimit` bigint(20) NOT NULL,
  `nonce` varchar(255) NOT NULL,
  `miner` char(42) NOT NULL,
  `size` float NOT NULL,
  `stateroothash` char(66) NOT NULL,
  `unclehash` char(66) NOT NULL,
  `txroothash` char(66) NOT NULL,
  `receiptroothash` char(66) NOT NULL,
  `extradata` blob,
  `inputdata` blob,
  `tx_num` int(10) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `config_vars`
--

CREATE TABLE `config_vars` (
  `id` int(11) NOT NULL,
  `name` varchar(25) NOT NULL,
  `value` varchar(125) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `config_vars`
--

INSERT INTO `config_vars` (`id`, `name`, `value`) VALUES
(1, 'next_check_block', '235667');

-- --------------------------------------------------------

--
-- 表的结构 `delivery_history`
--

CREATE TABLE `delivery_history` (
  `id` char(36) DEFAULT NULL,
  `client` char(42) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `endpoint` varchar(255) NOT NULL,
  `datalength` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `events`
--

CREATE TABLE `events` (
  `origin` char(42) NOT NULL,
  `index` bigint(20) NOT NULL,
  `topics` tinytext NOT NULL,
  `data` blob,
  `txhash` char(66) NOT NULL,
  `blockhash` char(66) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `nodes`
--

CREATE TABLE `nodes` (
  `id` int(11) NOT NULL,
  `owner_address` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `chequebook_address` varchar(100) CHARACTER SET utf8 DEFAULT NULL,
  `cpu_name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `cpu_score` int(11) NOT NULL DEFAULT '0',
  `local_ip` varchar(55) CHARACTER SET utf8 DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- 转存表中的数据 `nodes`
--

INSERT INTO `nodes` (`id`, `owner_address`, `chequebook_address`, `cpu_name`, `cpu_score`, `local_ip`, `status`, `last_updated`) VALUES
(1, '0xad53d6291353280458e04a63006f1e0484623b8f', '0x302d47dc68536ef28a2221b61b39d4048e217f37', NULL, 0, '47.52.211.206', 1, '2021-08-10 06:58:45'),
(2, '0x444af7c95c951ab8b382c8283da3ddd87450dd60', '0x4bbabc4486c1d1e9192e7906406f80ba58db6b83', 'Intel(R) Xeon(R) Platinum 8163 CPU @ 2.50GHz', 164296704, '47.52.211.206', 1, '2021-08-10 15:42:35'),
(3, '0xbed13479c186003fdf2dfc932c3467e7e4431a0e', '0xbc5ad86455b2f74b459576d3cd7dec91d88beb1d', 'Intel(R) Xeon(R) Platinum 8163 CPU @ 2.50GHz', 164296704, '47.52.211.206', 1, '2021-08-11 21:50:59');

-- --------------------------------------------------------

--
-- 表的结构 `signers`
--

CREATE TABLE `signers` (
  `id` int(11) NOT NULL,
  `address` varchar(78) NOT NULL,
  `ip` varchar(32) NOT NULL,
  `created_time` datetime NOT NULL,
  `country` varchar(55) NOT NULL,
  `status` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- 转存表中的数据 `signers`
--

INSERT INTO `signers` (`id`, `address`, `ip`, `created_time`, `country`, `status`) VALUES
(1, '0x00ac220f8c2aebb70b7844fb5cd4b0d13b14d228', '127.0.0.1', '2021-08-11 22:44:46', 'South Korea', 1),
(2, '0x07a239bca16674b91daf46a070e25380d92b75e4', '127.0.0.1', '2021-08-11 22:45:32', 'South Korea', 1),
(3, '0x7ea05a75a46ed507171ce75e4b22be0655e15fa6', '127.0.0.1', '2021-08-11 22:45:58', 'Singapore', 1),
(4, '0xeca06e789d57c884495c8232b0e59d43c97d3235', '127.0.0.1', '2021-08-11 22:46:29', 'USA', 1);

-- --------------------------------------------------------

--
-- 表的结构 `subscription_details`
--

CREATE TABLE `subscription_details` (
  `address` char(42) NOT NULL,
  `subscriptionplan` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `deliverycount` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `transactions`
--

CREATE TABLE `transactions` (
  `hash` char(66) NOT NULL,
  `from` char(42) NOT NULL,
  `to` char(42) DEFAULT NULL,
  `contract` char(42) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  `data` blob,
  `input_data` text,
  `gas` bigint(20) NOT NULL,
  `gasprice` varchar(255) NOT NULL,
  `cost` varchar(255) NOT NULL,
  `nonce` bigint(20) NOT NULL,
  `state` smallint(6) NOT NULL,
  `blockhash` char(66) NOT NULL,
  `blockNumber` bigint(20) NOT NULL DEFAULT '0',
  `timestamp` bigint(20) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

CREATE TABLE `users` (
  `address` char(42) NOT NULL,
  `apikey` char(66) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `enabled` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- 转存表中的数据 `users`
--

INSERT INTO `users` (`address`, `apikey`, `ts`, `enabled`) VALUES
('0x4493Ea4F6c7d25d13B15b06C7694C1a97213Db3c', '0x51d9a52d29c99b6bde0f118fdd829097d18a9f041fc6fa661ace13cb93b7f389', '2021-08-01 10:18:27', 1);

--
-- 转储表的索引
--

--
-- 表的索引 `blocks`
--
ALTER TABLE `blocks`
  ADD PRIMARY KEY (`hash`),
  ADD UNIQUE KEY `number` (`number`),
  ADD UNIQUE KEY `number_2` (`number`),
  ADD KEY `idx_blocks_number` (`number`),
  ADD KEY `idx_blocks_time` (`time`);

--
-- 表的索引 `config_vars`
--
ALTER TABLE `config_vars`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `events`
--
ALTER TABLE `events`
  ADD KEY `idx_events_origin` (`origin`),
  ADD KEY `idx_events_transaction_hash` (`txhash`);

--
-- 表的索引 `nodes`
--
ALTER TABLE `nodes`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `signers`
--
ALTER TABLE `signers`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `subscription_details`
--
ALTER TABLE `subscription_details`
  ADD PRIMARY KEY (`address`);

--
-- 表的索引 `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `deliverycount` (`deliverycount`);

--
-- 表的索引 `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`hash`),
  ADD KEY `idx_transactions_from` (`from`),
  ADD KEY `idx_transactions_to` (`to`),
  ADD KEY `idx_transactions_contract` (`contract`),
  ADD KEY `idx_transactions_nonce` (`nonce`),
  ADD KEY `idx_transactions_block_hash` (`blockhash`);

--
-- 表的索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`apikey`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `config_vars`
--
ALTER TABLE `config_vars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `nodes`
--
ALTER TABLE `nodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `signers`
--
ALTER TABLE `signers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用表AUTO_INCREMENT `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
