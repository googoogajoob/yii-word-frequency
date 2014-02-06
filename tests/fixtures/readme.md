Testing Setup
=============

Mysql table names which use a prefix must have the prefix applied to the filename here as the Yii fixture manager does not make any distinction for table name prefixes.

The Active Record Model Classes however, do make this distinction. Thus, the $prefixes array within tests 
must either use the prefix when accessing a tablename directly (i.e. when using the colon ":") or refer to
the model class name, which liely does not have the prefix.

The test data from the DB is only need as read only data. 
Therefore init.php is used to prepare the tables once at the beginning of all tests.
The initialization and reloading of the tables is prevented by define the public "fixtures" variable as an empty array.
