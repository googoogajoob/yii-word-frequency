Mysql table names which use a prefix must have the prefix applied to the filename here as the Yii fixture manager does not make any distinction for table name prefixes.

The Active Record Model Classes however, do make this distinction. Thus, the $prefixes array within tests 
must either use the prefix when accessing a tablename directly (i.e. when using the colon ":") or refer to
the model class name, which liely does not have the prefix.
