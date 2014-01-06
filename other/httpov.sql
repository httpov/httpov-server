-- MySQL dump 10.11
--
-- Host: localhost    Database: httpov
-- ------------------------------------------------------
-- Server version	5.0.48

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `httpov`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `httpov` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `httpov`;

--
-- Table structure for table `batch`
--

DROP TABLE IF EXISTS `batch`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `batch` (
  `id` int(11) NOT NULL auto_increment,
  `job` int(11) NOT NULL,
  `frame` int(11) NOT NULL,
  `slice` int(11) NOT NULL,
  `count` int(11) NOT NULL,
  `issued` int(11) NOT NULL,
  `finished` int(11) NOT NULL,
  `aborted` int(11) NOT NULL,
  `client` varchar(50) NOT NULL,
  `cid` int(11) NOT NULL,
  `cgroup` varchar(50) NOT NULL,
  `active` int(11) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `job`
--

DROP TABLE IF EXISTS `job`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `job` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(50) NOT NULL,
  `frames` int(11) NOT NULL,
  `rows` int(11) NOT NULL,
  `sliced` int(11) NOT NULL,
  `count` int(11) NOT NULL,
  `clients` int(11) NOT NULL,
  `current` int(11) NOT NULL,
  `slice` int(11) NOT NULL,
  `issued` int(11) NOT NULL,
  `finished` int(11) NOT NULL,
  `aborted` int(11) NOT NULL,
  `locked` int(11) NOT NULL,
  `firstbatch` int(11) NOT NULL,
  `lastbatch` int(11) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;
SET character_set_client = @saved_cs_client;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2007-12-28 18:06:36
