{
  "connections": [
    {
      "connection": "primary",
      "database": "nextgennoise",
      "tables": [
        {
          "table": "adlocations",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Price",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "adlocations",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 6,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `adlocations` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(255) NOT NULL,\n  `Price` int NOT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1"
        },
        {
          "table": "ads",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(200)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Url",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Location",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Active",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "StartDate",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "EndDate",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0000-00-00 00:00:00",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "DesktopImage",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "MobileImage",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ContentAdHorizontal",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ContentAdHorizontalMobile",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Callout",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Hits",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "ads",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 3,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `ads` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(200) NOT NULL,\n  `Slug` varchar(255) NOT NULL,\n  `Url` varchar(255) NOT NULL,\n  `Location` varchar(255) NOT NULL,\n  `Active` int DEFAULT '0',\n  `StartDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `EndDate` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',\n  `DesktopImage` varchar(255) DEFAULT NULL,\n  `MobileImage` varchar(255) DEFAULT NULL,\n  `ContentAdHorizontal` varchar(255) DEFAULT NULL,\n  `ContentAdHorizontalMobile` varchar(255) DEFAULT NULL,\n  `Callout` text,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Hits` text,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=latin1"
        },
        {
          "table": "apikeys",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UserId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Type",
              "Type": "varchar(255)",
              "Collation": "utf8mb3_general_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Value",
              "Type": "varchar(255)",
              "Collation": "utf8mb3_general_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "apikeys",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 1,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `apikeys` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `UserId` int NOT NULL,\n  `Type` varchar(255) NOT NULL,\n  `Value` varchar(255) NOT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3"
        },
        {
          "table": "claimstatuses",
          "columns": [
            {
              "Field": "id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "claimstatuses",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "id",
              "Collation": "A",
              "Cardinality": 5,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `claimstatuses` (\n  `id` int NOT NULL AUTO_INCREMENT,\n  `Title` varchar(255) NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=latin1"
        },
        {
          "table": "contacts",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "FirstName",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "LastName",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Email",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Phone",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Address",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Status",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Birthday",
              "Type": "datetime",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Company",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Band",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "contacts",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 4,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `contacts` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `FirstName` varchar(255) DEFAULT NULL,\n  `LastName` varchar(255) DEFAULT NULL,\n  `Email` varchar(255) NOT NULL,\n  `Phone` varchar(255) DEFAULT NULL,\n  `Address` varchar(255) DEFAULT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Status` varchar(255) DEFAULT NULL,\n  `Birthday` datetime DEFAULT NULL,\n  `Company` varchar(255) DEFAULT NULL,\n  `Band` varchar(255) DEFAULT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=latin1"
        },
        {
          "table": "donations",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Email",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "datetime",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "OrderNumber",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Type",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Amount",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Currency",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "donations",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 0,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `donations` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Email` varchar(255) NOT NULL,\n  `Created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `OrderNumber` varchar(255) NOT NULL,\n  `Type` varchar(255) NOT NULL,\n  `Amount` varchar(255) NOT NULL,\n  `Currency` varchar(255) NOT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB DEFAULT CHARSET=latin1"
        },
        {
          "table": "genres",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "utf8mb3_general_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "varchar(255)",
              "Collation": "utf8mb3_general_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Tags",
              "Type": "text",
              "Collation": "utf8mb3_general_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Summary",
              "Type": "text",
              "Collation": "utf8mb3_general_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Body",
              "Type": "text",
              "Collation": "utf8mb3_general_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Author",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "genres",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 24,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `genres` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `Title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,\n  `Slug` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,\n  `Tags` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,\n  `Summary` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,\n  `Body` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Author` int NOT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb3"
        },
        {
          "table": "hits",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Timestamp",
              "Type": "datetime",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UserId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Action",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UserAgent",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Referrer",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "PageUrl",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "IPAddress",
              "Type": "varchar(45)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "SessionId",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "OtherData",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ViewCount",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "1",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "EntityId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Location",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "hits",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 281555,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `hits` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `Timestamp` datetime NOT NULL,\n  `UserId` int DEFAULT NULL,\n  `Action` varchar(255) NOT NULL,\n  `UserAgent` varchar(255) DEFAULT NULL,\n  `Referrer` varchar(255) DEFAULT NULL,\n  `PageUrl` varchar(255) DEFAULT NULL,\n  `IPAddress` varchar(45) DEFAULT NULL,\n  `SessionId` varchar(255) DEFAULT NULL,\n  `OtherData` text,\n  `ViewCount` int NOT NULL DEFAULT '1',\n  `EntityId` int DEFAULT NULL,\n  `Location` varchar(255) DEFAULT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=395314 DEFAULT CHARSET=latin1"
        },
        {
          "table": "jwt_tokens",
          "columns": [
            {
              "Field": "id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "token",
              "Type": "text",
              "Collation": "utf8mb3_general_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "user_id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "expiration",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "jwt_tokens",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "id",
              "Collation": "A",
              "Cardinality": 0,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `jwt_tokens` (\n  `id` int NOT NULL AUTO_INCREMENT,\n  `token` text NOT NULL,\n  `user_id` int NOT NULL,\n  `expiration` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3"
        },
        {
          "table": "linklocations",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(200)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "linklocations",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 1,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `linklocations` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(200) NOT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1"
        },
        {
          "table": "links",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(200)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Url",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Location",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Active",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "links",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 1,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `links` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(200) NOT NULL,\n  `Url` varchar(255) NOT NULL,\n  `Location` varchar(255) NOT NULL,\n  `Active` varchar(255) DEFAULT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1"
        },
        {
          "table": "ngn_migrations",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Filename",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "UNI",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "AppliedAt",
              "Type": "datetime",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "ngn_migrations",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 3,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            },
            {
              "Table": "ngn_migrations",
              "Non_unique": 0,
              "Key_name": "Filename",
              "Seq_in_index": 1,
              "Column_name": "Filename",
              "Collation": "A",
              "Cardinality": 3,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `ngn_migrations` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `Filename` varchar(255) NOT NULL,\n  `AppliedAt` datetime NOT NULL,\n  PRIMARY KEY (`Id`),\n  UNIQUE KEY `Filename` (`Filename`)\n) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        },
        {
          "table": "orders",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Number",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Cart",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Shipping",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Customer",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Stripe",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "IPAddress",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Email",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Status",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "1",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "orders",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 2,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `orders` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `Number` varchar(255) NOT NULL,\n  `Cart` text NOT NULL,\n  `Shipping` text,\n  `Customer` text,\n  `Stripe` text,\n  `IPAddress` varchar(255) NOT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Email` varchar(255) NOT NULL,\n  `Status` int NOT NULL DEFAULT '1',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=latin1"
        },
        {
          "table": "orderstatuses",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Body",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "orderstatuses",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 6,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `orderstatuses` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(255) NOT NULL,\n  `Body` text,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1"
        },
        {
          "table": "pages",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(200)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Body",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Tags",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Summary",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Published",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ShowInNavigation",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "pages",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 0,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `pages` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(200) NOT NULL,\n  `Slug` varchar(255) NOT NULL,\n  `Body` text NOT NULL,\n  `Tags` varchar(255) DEFAULT NULL,\n  `Summary` varchar(255) DEFAULT NULL,\n  `Published` int DEFAULT '0',\n  `ShowInNavigation` int DEFAULT '0',\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB DEFAULT CHARSET=latin1"
        },
        {
          "table": "pendingclaims",
          "columns": [
            {
              "Field": "id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Type",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UserId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Email",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Phone",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Company",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Facebook",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Instagram",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "StatusId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "1",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Relationship",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "SocialAgree",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "RightsAgree",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Code",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "pendingclaims",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "id",
              "Collation": "A",
              "Cardinality": 3839,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `pendingclaims` (\n  `id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Type` varchar(255) NOT NULL,\n  `UserId` int NOT NULL,\n  `Title` varchar(255) NOT NULL,\n  `Email` varchar(255) NOT NULL,\n  `Phone` varchar(255) NOT NULL,\n  `Company` varchar(255) DEFAULT NULL,\n  `Facebook` varchar(255) NOT NULL,\n  `Instagram` varchar(255) NOT NULL,\n  `StatusId` int NOT NULL DEFAULT '1',\n  `Relationship` varchar(255) NOT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `SocialAgree` int NOT NULL DEFAULT '0',\n  `RightsAgree` int NOT NULL DEFAULT '0',\n  `Code` varchar(255) NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB AUTO_INCREMENT=9381 DEFAULT CHARSET=latin1"
        },
        {
          "table": "polls",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Question",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Options",
              "Type": "longtext",
              "Collation": "utf8mb4_bin",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Votes",
              "Type": "longtext",
              "Collation": "utf8mb4_bin",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "polls",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 0,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `polls` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `Question` varchar(255) NOT NULL,\n  `Options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,\n  `Votes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB DEFAULT CHARSET=latin1"
        },
        {
          "table": "postmentions",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "PostId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ArtistId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "LabelId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "FoundIn",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Timestamp",
              "Type": "datetime",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "postmentions",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 314,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `postmentions` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `PostId` int NOT NULL,\n  `ArtistId` int DEFAULT NULL,\n  `LabelId` int DEFAULT NULL,\n  `FoundIn` varchar(255) NOT NULL,\n  `Timestamp` datetime NOT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=315 DEFAULT CHARSET=latin1"
        },
        {
          "table": "posts",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(200)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Body",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Tags",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Summary",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "TypeId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "1",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Published",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Featured",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Image",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Author",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "PublishedDate",
              "Type": "datetime",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "IsUser",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "posts",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 68,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `posts` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(200) NOT NULL,\n  `Slug` varchar(255) NOT NULL,\n  `Body` text NOT NULL,\n  `Tags` text,\n  `Summary` varchar(255) DEFAULT NULL,\n  `TypeId` int DEFAULT '1',\n  `Published` int DEFAULT '0',\n  `Featured` int DEFAULT '0',\n  `Image` varchar(255) DEFAULT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Author` int NOT NULL,\n  `PublishedDate` datetime DEFAULT NULL,\n  `IsUser` int NOT NULL DEFAULT '0',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=latin1"
        },
        {
          "table": "posttypes",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Section",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "posttypes",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 11,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `posttypes` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(255) NOT NULL,\n  `Section` varchar(255) NOT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1"
        },
        {
          "table": "promos",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "utf8mb3_general_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "WeekOf",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UserId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Data",
              "Type": "text",
              "Collation": "utf8mb3_general_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Active",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "promos",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 1,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `promos` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `Title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,\n  `WeekOf` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `UserId` int NOT NULL,\n  `Data` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,\n  `Active` int NOT NULL DEFAULT '0',\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3"
        },
        {
          "table": "radiospins",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Artist",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Song",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Timestamp",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "StationId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Approved",
              "Type": "tinyint(1)",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Misc",
              "Type": "text",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "TWS",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Program",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Hotlist",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "radiospins",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 63,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `radiospins` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `Artist` varchar(255) NOT NULL,\n  `Song` varchar(255) NOT NULL,\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `StationId` int NOT NULL,\n  `Approved` tinyint(1) NOT NULL DEFAULT '0',\n  `Misc` text,\n  `TWS` int NOT NULL,\n  `Program` varchar(255) DEFAULT NULL,\n  `Hotlist` int NOT NULL DEFAULT '0',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        },
        {
          "table": "releases",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ArtistId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "LabelId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Body",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Tags",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Summary",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Type",
              "Type": "enum('album','ep','single')",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ReleaseDate",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Genre",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Image",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ListeningURL",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "WatchURL",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "releases",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 7,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `releases` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `ArtistId` int NOT NULL,\n  `LabelId` int NOT NULL,\n  `Title` varchar(255) NOT NULL,\n  `Slug` varchar(255) NOT NULL,\n  `Body` text,\n  `Tags` text,\n  `Summary` varchar(255) DEFAULT NULL,\n  `Type` enum('album','ep','single') NOT NULL,\n  `ReleaseDate` timestamp NOT NULL,\n  `Genre` varchar(255) DEFAULT NULL,\n  `Image` varchar(255) DEFAULT NULL,\n  `ListeningURL` varchar(255) DEFAULT NULL,\n  `WatchURL` varchar(255) DEFAULT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=latin1"
        },
        {
          "table": "shows",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ArtistId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "OtherArtists",
              "Type": "text",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "VenueId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ShowDate",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ConfirmedAttendance",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Image",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "shows",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 1,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `shows` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `ArtistId` int NOT NULL,\n  `OtherArtists` text,\n  `VenueId` int DEFAULT NULL,\n  `ShowDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `ConfirmedAttendance` int NOT NULL DEFAULT '0',\n  `Image` varchar(255) DEFAULT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        },
        {
          "table": "slides",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(200)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Url",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Location",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "StartDate",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "EndDate",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "DesktopImage",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "MobileImage",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Misc",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "slides",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 0,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `slides` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(200) NOT NULL,\n  `Url` varchar(255) NOT NULL,\n  `Location` text,\n  `StartDate` varchar(255) NOT NULL,\n  `EndDate` varchar(255) DEFAULT NULL,\n  `DesktopImage` varchar(255) DEFAULT NULL,\n  `MobileImage` varchar(255) DEFAULT NULL,\n  `Misc` text,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB DEFAULT CHARSET=latin1"
        },
        {
          "table": "socialmediaposts",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ArtistId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Platform",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "PostId",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Timestamp",
              "Type": "datetime",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Content",
              "Type": "text",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "EngagementMetrics",
              "Type": "longtext",
              "Collation": "utf8mb4_bin",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "socialmediaposts",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 0,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `socialmediaposts` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `ArtistId` int NOT NULL,\n  `Platform` varchar(255) NOT NULL,\n  `PostId` varchar(255) NOT NULL,\n  `Timestamp` datetime NOT NULL,\n  `Content` text,\n  `EngagementMetrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        },
        {
          "table": "songs",
          "columns": [
            {
              "Field": "id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ArtistId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ReleaseId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ReleaseDate",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0000-00-00 00:00:00",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Published",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Featured",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Links",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "mp3",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Genre",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Tags",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Summary",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "songs",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "id",
              "Collation": "A",
              "Cardinality": 2,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `songs` (\n  `id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(255) NOT NULL,\n  `Slug` varchar(255) NOT NULL,\n  `ArtistId` int NOT NULL,\n  `ReleaseId` int NOT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `ReleaseDate` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',\n  `Published` int NOT NULL DEFAULT '0',\n  `Featured` int NOT NULL DEFAULT '0',\n  `Links` text,\n  `mp3` varchar(255) DEFAULT NULL,\n  `Genre` int DEFAULT NULL,\n  `Tags` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,\n  `Summary` text CHARACTER SET latin1 COLLATE latin1_swedish_ci,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1"
        },
        {
          "table": "spins",
          "columns": [
            {
              "Field": "Id",
              "Type": "bigint unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UserId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "SongId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "SessionId",
              "Type": "char(36)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "StartTime",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "EndTime",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0000-00-00 00:00:00",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "DurationListened",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "PercentagePlayed",
              "Type": "decimal(5,2)",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0.00",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "IsFullPlay",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "SkipStatus",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "IsRepeat",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "PlaybackPosition",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Platform",
              "Type": "varchar(50)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": "web",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "DeviceType",
              "Type": "varchar(50)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "IpAddress",
              "Type": "varchar(45)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UserAgent",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "NetworkType",
              "Type": "varchar(50)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ConnectionSpeed",
              "Type": "decimal(10,2)",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "BufferingTime",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "spins",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 7,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `spins` (\n  `Id` bigint unsigned NOT NULL AUTO_INCREMENT,\n  `UserId` int DEFAULT '0',\n  `SongId` int NOT NULL,\n  `SessionId` char(36) NOT NULL,\n  `StartTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `EndTime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',\n  `DurationListened` int DEFAULT '0',\n  `PercentagePlayed` decimal(5,2) DEFAULT '0.00',\n  `IsFullPlay` int DEFAULT '0',\n  `SkipStatus` int DEFAULT '0',\n  `IsRepeat` int DEFAULT '0',\n  `PlaybackPosition` int DEFAULT '0',\n  `Platform` varchar(50) DEFAULT 'web',\n  `DeviceType` varchar(50) DEFAULT NULL,\n  `IpAddress` varchar(45) DEFAULT NULL,\n  `UserAgent` text,\n  `NetworkType` varchar(50) DEFAULT NULL,\n  `ConnectionSpeed` decimal(10,2) DEFAULT NULL,\n  `BufferingTime` int DEFAULT '0',\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=latin1"
        },
        {
          "table": "stations",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Name",
              "Type": "varchar(190)",
              "Collation": "utf8mb4_unicode_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "varchar(190)",
              "Collation": "utf8mb4_unicode_ci",
              "Null": "NO",
              "Key": "UNI",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Market",
              "Type": "varchar(190)",
              "Collation": "utf8mb4_unicode_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Timezone",
              "Type": "varchar(64)",
              "Collation": "utf8mb4_unicode_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ContactEmail",
              "Type": "varchar(190)",
              "Collation": "utf8mb4_unicode_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "IsActive",
              "Type": "tinyint(1)",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "1",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "CreatedAt",
              "Type": "datetime",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UpdatedAt",
              "Type": "datetime",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "stations",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 0,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            },
            {
              "Table": "stations",
              "Non_unique": 0,
              "Key_name": "uq_stations_slug",
              "Seq_in_index": 1,
              "Column_name": "Slug",
              "Collation": "A",
              "Cardinality": 0,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `stations` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,\n  `Slug` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,\n  `Market` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,\n  `Timezone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,\n  `ContactEmail` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,\n  `IsActive` tinyint(1) NOT NULL DEFAULT '1',\n  `CreatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `UpdatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`),\n  UNIQUE KEY `uq_stations_slug` (`Slug`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        },
        {
          "table": "tokens",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UserId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "LongLivedToken",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "PageAccessToken",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Host",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": "facebook",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "PageId",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "PageName",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "IGBusinessName",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "IGBusinessAccount",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ExpirationDate",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0000-00-00 00:00:00",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "tokens",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 1,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `tokens` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `UserId` int NOT NULL,\n  `LongLivedToken` text NOT NULL,\n  `PageAccessToken` text,\n  `Host` varchar(255) NOT NULL DEFAULT 'facebook',\n  `PageId` varchar(255) DEFAULT NULL,\n  `PageName` varchar(255) DEFAULT NULL,\n  `IGBusinessName` varchar(255) DEFAULT NULL,\n  `IGBusinessAccount` varchar(255) DEFAULT NULL,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `ExpirationDate` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=latin1"
        },
        {
          "table": "userroles",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "userroles",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 11,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `userroles` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Title` varchar(255) NOT NULL,\n  `Slug` text NOT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=latin1"
        },
        {
          "table": "users",
          "columns": [
            {
              "Field": "Id",
              "Type": "int unsigned",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "StatusId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "1",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(30)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "OwnerTitle",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Email",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Body",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Password",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "RoleId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "LabelId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ArtistId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "StationId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Phone",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Address",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Image",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "VerifiedPhone",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "VerifiedEmail",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Misc",
              "Type": "longblob",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Claimed",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Source",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "FacebookUrl",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "InstagramUrl",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "YoutubeUrl",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "TiktokUrl",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "WebsiteUrl",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "FacebookId",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "InstagramId",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "YoutubeId",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "SpotifyId",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "TiktokId",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Spotlight",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "0",
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "users",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 1344,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `users` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `StatusId` int NOT NULL DEFAULT '1',\n  `Title` varchar(30) NOT NULL,\n  `OwnerTitle` varchar(255) DEFAULT NULL,\n  `Slug` varchar(255) DEFAULT NULL,\n  `Email` varchar(255) NOT NULL,\n  `Body` text,\n  `Password` varchar(255) NOT NULL,\n  `RoleId` int NOT NULL,\n  `LabelId` int DEFAULT NULL,\n  `ArtistId` int DEFAULT NULL,\n  `StationId` int DEFAULT NULL,\n  `Phone` varchar(255) DEFAULT NULL,\n  `Address` varchar(255) DEFAULT NULL,\n  `Image` varchar(255) DEFAULT NULL,\n  `VerifiedPhone` int DEFAULT '0',\n  `VerifiedEmail` int DEFAULT '0',\n  `Misc` longblob,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NULL DEFAULT NULL,\n  `Claimed` int NOT NULL DEFAULT '0',\n  `Source` varchar(255) DEFAULT NULL,\n  `FacebookUrl` varchar(255) DEFAULT NULL,\n  `InstagramUrl` varchar(255) DEFAULT NULL,\n  `YoutubeUrl` varchar(255) DEFAULT NULL,\n  `TiktokUrl` varchar(255) DEFAULT NULL,\n  `WebsiteUrl` varchar(255) DEFAULT NULL,\n  `FacebookId` varchar(255) DEFAULT NULL,\n  `InstagramId` varchar(255) DEFAULT NULL,\n  `YoutubeId` varchar(255) DEFAULT NULL,\n  `SpotifyId` varchar(255) DEFAULT NULL,\n  `TiktokId` varchar(255) DEFAULT NULL,\n  `Spotlight` int NOT NULL DEFAULT '0',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=1795 DEFAULT CHARSET=latin1"
        },
        {
          "table": "userstatuses",
          "columns": [
            {
              "Field": "id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "title",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "body",
              "Type": "text",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "userstatuses",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "id",
              "Collation": "A",
              "Cardinality": 6,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `userstatuses` (\n  `id` int NOT NULL AUTO_INCREMENT,\n  `title` varchar(255) NOT NULL,\n  `body` text,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1"
        },
        {
          "table": "verificationcodes",
          "columns": [
            {
              "Field": "id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Code",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Expiration",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Type",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "UserId",
              "Type": "int",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Email",
              "Type": "varchar(255)",
              "Collation": "latin1_swedish_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "verificationcodes",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "id",
              "Collation": "A",
              "Cardinality": 3951,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `verificationcodes` (\n  `id` int NOT NULL AUTO_INCREMENT,\n  `Code` varchar(255) NOT NULL,\n  `Expiration` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `Type` varchar(255) NOT NULL,\n  `UserId` int DEFAULT NULL,\n  `Email` varchar(255) DEFAULT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB AUTO_INCREMENT=9342 DEFAULT CHARSET=latin1"
        },
        {
          "table": "videos",
          "columns": [
            {
              "Field": "Id",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "PRI",
              "Default": null,
              "Extra": "auto_increment",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ArtistId",
              "Type": "int",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Platform",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "VideoId",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Title",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Slug",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Body",
              "Type": "text",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Summary",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Tags",
              "Type": "varchar(255)",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "NO",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Misc",
              "Type": "text",
              "Collation": "utf8mb4_0900_ai_ci",
              "Null": "YES",
              "Key": "",
              "Default": null,
              "Extra": "",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Created",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "Updated",
              "Type": "timestamp",
              "Collation": null,
              "Null": "NO",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            },
            {
              "Field": "ReleaseDate",
              "Type": "timestamp",
              "Collation": null,
              "Null": "YES",
              "Key": "",
              "Default": "CURRENT_TIMESTAMP",
              "Extra": "DEFAULT_GENERATED",
              "Privileges": "select,insert,update,references",
              "Comment": ""
            }
          ],
          "indexes": [
            {
              "Table": "videos",
              "Non_unique": 0,
              "Key_name": "PRIMARY",
              "Seq_in_index": 1,
              "Column_name": "Id",
              "Collation": "A",
              "Cardinality": 26,
              "Sub_part": null,
              "Packed": null,
              "Null": "",
              "Index_type": "BTREE",
              "Comment": "",
              "Index_comment": "",
              "Visible": "YES",
              "Expression": null
            }
          ],
          "create": "CREATE TABLE `videos` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `ArtistId` int NOT NULL,\n  `Platform` varchar(255) NOT NULL,\n  `VideoId` varchar(255) NOT NULL,\n  `Title` varchar(255) NOT NULL,\n  `Slug` varchar(255) NOT NULL,\n  `Body` text,\n  `Summary` varchar(255) NOT NULL,\n  `Tags` varchar(255) NOT NULL,\n  `Misc` text,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `ReleaseDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
        }
      ]
    }
  ]
}

{
"connections": [
{
"connection": "ngnrankings",
"database": "ngnrankings",
"tables": [
{
"table": "artists",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ArtistId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Label_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "artists",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 913,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `artists` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `ArtistId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Label_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` int NOT NULL DEFAULT '0',\n  `Releases_Score_Historic` int NOT NULL DEFAULT '0',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=914 DEFAULT CHARSET=latin1"
},
{
"table": "artistsdaily",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ArtistId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Label_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "artistsdaily",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 65313,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `artistsdaily` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `ArtistId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Label_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Releases_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=202399 DEFAULT CHARSET=latin1"
},
{
"table": "artistsmonthly",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ArtistId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Label_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "artistsmonthly",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 41205,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `artistsmonthly` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `ArtistId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Label_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Releases_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=41398 DEFAULT CHARSET=latin1"
},
{
"table": "artistsweekly",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ArtistId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Label_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "artistsweekly",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 0,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `artistsweekly` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `ArtistId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Label_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Releases_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB DEFAULT CHARSET=latin1"
},
{
"table": "artistsyearly",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ArtistId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Label_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "artistsyearly",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 0,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `artistsyearly` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `ArtistId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Label_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Releases_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB DEFAULT CHARSET=latin1"
},
{
"table": "cache",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "CachedAt",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "cache",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 0,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `cache` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `CachedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1"
},
{
"table": "labels",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "LabelId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Artist_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "AgeScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ReputationScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "labels",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 290,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `labels` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `LabelId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Artist_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `AgeScore` decimal(10,2) DEFAULT NULL,\n  `ReputationScore` decimal(10,2) DEFAULT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=291 DEFAULT CHARSET=latin1"
},
{
"table": "labelsdaily",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "LabelId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Artist_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "AgeScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ReputationScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "labelsdaily",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 19662,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `labelsdaily` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `LabelId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Artist_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `AgeScore` decimal(10,2) DEFAULT NULL,\n  `ReputationScore` decimal(10,2) DEFAULT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=65654 DEFAULT CHARSET=latin1"
},
{
"table": "labelsmonthly",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "LabelId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Artist_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "AgeScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ReputationScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "labelsmonthly",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 17403,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `labelsmonthly` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `LabelId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Artist_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `AgeScore` decimal(10,2) DEFAULT NULL,\n  `ReputationScore` decimal(10,2) DEFAULT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=17913 DEFAULT CHARSET=latin1"
},
{
"table": "labelsweekly",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "LabelId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Artist_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "AgeScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ReputationScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "labelsweekly",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 0,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `labelsweekly` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `LabelId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Artist_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `AgeScore` decimal(10,2) DEFAULT NULL,\n  `ReputationScore` decimal(10,2) DEFAULT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB DEFAULT CHARSET=latin1"
},
{
"table": "labelsyearly",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "LabelId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Artist_Boost_Score",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED on update CURRENT_TIMESTAMP",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "SMR_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Post_Mentions_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Views_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Social_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Releases_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Posts_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Videos_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Active",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Spins_Score_Historic",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": "0.00",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "AgeScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "ReputationScore",
"Type": "decimal(10,2)",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "labelsyearly",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 0,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `labelsyearly` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `LabelId` int NOT NULL,\n  `Score` decimal(10,2) NOT NULL,\n  `Artist_Boost_Score` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  `SMR_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `SMR_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Post_Mentions_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Views_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Social_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Releases_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Posts_Score_Active` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Posts_Score_Historic` decimal(10,2) NOT NULL DEFAULT '0.00',\n  `Videos_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Videos_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Active` decimal(10,2) DEFAULT '0.00',\n  `Spins_Score_Historic` decimal(10,2) DEFAULT '0.00',\n  `AgeScore` decimal(10,2) DEFAULT NULL,\n  `ReputationScore` decimal(10,2) DEFAULT NULL,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB DEFAULT CHARSET=latin1"
}
]
}
]
}

{
"connections": [
{
"connection": "smrrankings",
"database": "smr_charts",
"tables": [
{
"table": "chartdata",
"columns": [
{
"Field": "Id",
"Type": "int unsigned",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Artists",
"Type": "varchar(255)",
"Collation": "latin1_swedish_ci",
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Song",
"Type": "varchar(255)",
"Collation": "latin1_swedish_ci",
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Label",
"Type": "varchar(255)",
"Collation": "latin1_swedish_ci",
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "WOC",
"Type": "varchar(255)",
"Collation": "latin1_swedish_ci",
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "LWP",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "TWP",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Date",
"Type": "datetime",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Peak",
"Type": "int",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "TWS",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "LWS",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Difference",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Adds",
"Type": "int",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "StationsOn",
"Type": "varchar(50)",
"Collation": "latin1_swedish_ci",
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "chartdata",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 22613,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
},
{
"Table": "chartdata",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 2,
"Column_name": "Date",
"Collation": "A",
"Cardinality": 22613,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `chartdata` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Artists` varchar(255) NOT NULL,\n  `Song` varchar(255) NOT NULL,\n  `Label` varchar(255) NOT NULL,\n  `WOC` varchar(255) DEFAULT NULL,\n  `LWP` int NOT NULL,\n  `TWP` int NOT NULL,\n  `Date` datetime NOT NULL,\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Peak` int DEFAULT NULL,\n  `TWS` int NOT NULL,\n  `LWS` int NOT NULL,\n  `Difference` int NOT NULL,\n  `Adds` int DEFAULT NULL,\n  `StationsOn` varchar(50) DEFAULT NULL,\n  PRIMARY KEY (`Id`,`Date`)\n) ENGINE=InnoDB AUTO_INCREMENT=24349 DEFAULT CHARSET=latin1\n/*!50100 PARTITION BY RANGE (year(`Date`))\n(PARTITION p2013 VALUES LESS THAN (2014) ENGINE = InnoDB,\n PARTITION p2014 VALUES LESS THAN (2015) ENGINE = InnoDB,\n PARTITION p2015 VALUES LESS THAN (2016) ENGINE = InnoDB,\n PARTITION p2016 VALUES LESS THAN (2017) ENGINE = InnoDB,\n PARTITION p2017 VALUES LESS THAN (2018) ENGINE = InnoDB,\n PARTITION p2018 VALUES LESS THAN (2019) ENGINE = InnoDB,\n PARTITION p2019 VALUES LESS THAN (2020) ENGINE = InnoDB,\n PARTITION p2020 VALUES LESS THAN (2021) ENGINE = InnoDB,\n PARTITION p2021 VALUES LESS THAN (2022) ENGINE = InnoDB,\n PARTITION p2022 VALUES LESS THAN (2023) ENGINE = InnoDB,\n PARTITION p2023 VALUES LESS THAN (2024) ENGINE = InnoDB,\n PARTITION p_future VALUES LESS THAN MAXVALUE ENGINE = InnoDB) */"
}
]
}
]
}

{
"connections": [
{
"connection": "ngnspins",
"database": "ngnspins",
"tables": [
{
"table": "spindata",
"columns": [
{
"Field": "Id",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Artist",
"Type": "varchar(255)",
"Collation": "utf8mb4_0900_ai_ci",
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Song",
"Type": "varchar(255)",
"Collation": "utf8mb4_0900_ai_ci",
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Timestamp",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "StationId",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Approved",
"Type": "tinyint(1)",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Misc",
"Type": "text",
"Collation": "utf8mb4_0900_ai_ci",
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "TWS",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Program",
"Type": "varchar(255)",
"Collation": "utf8mb4_0900_ai_ci",
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Hotlist",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "0",
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "spindata",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 1920,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `spindata` (\n  `Id` int NOT NULL AUTO_INCREMENT,\n  `Artist` varchar(255) NOT NULL,\n  `Song` varchar(255) NOT NULL,\n  `Timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `StationId` int NOT NULL,\n  `Approved` tinyint(1) NOT NULL DEFAULT '0',\n  `Misc` text,\n  `TWS` int NOT NULL,\n  `Program` varchar(255) DEFAULT NULL,\n  `Hotlist` int NOT NULL DEFAULT '0',\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=2806 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci"
}
]
}
]
}

{
"connections": [
{
"connection": "ngnnotes",
"database": "ngnnotes",
"tables": [
{
"table": "development",
"columns": [
{
"Field": "Id",
"Type": "int unsigned",
"Collation": null,
"Null": "NO",
"Key": "PRI",
"Default": null,
"Extra": "auto_increment",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Author",
"Type": "int",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Contents",
"Type": "blob",
"Collation": null,
"Null": "YES",
"Key": "",
"Default": null,
"Extra": "",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Created",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED",
"Privileges": "select,insert,update,references",
"Comment": ""
},
{
"Field": "Updated",
"Type": "timestamp",
"Collation": null,
"Null": "NO",
"Key": "",
"Default": "CURRENT_TIMESTAMP",
"Extra": "DEFAULT_GENERATED",
"Privileges": "select,insert,update,references",
"Comment": ""
}
],
"indexes": [
{
"Table": "development",
"Non_unique": 0,
"Key_name": "PRIMARY",
"Seq_in_index": 1,
"Column_name": "Id",
"Collation": "A",
"Cardinality": 0,
"Sub_part": null,
"Packed": null,
"Null": "",
"Index_type": "BTREE",
"Comment": "",
"Index_comment": "",
"Visible": "YES",
"Expression": null
}
],
"create": "CREATE TABLE `development` (\n  `Id` int unsigned NOT NULL AUTO_INCREMENT,\n  `Author` int NOT NULL,\n  `Contents` blob,\n  `Created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  `Updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n  PRIMARY KEY (`Id`)\n) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb3"
}
]
}
]
}