

create database dbName;
create user userName@% IDENTIFIED BY 'userPassword';

-- 赋于部分权限
grant select,update,delete,insert,alter,create,drop,EXECUTE on dbName.* to userName@'%';

-- 全部权限
grant * on dbName.* to userName@%;

flush privileges;
use dbName;

