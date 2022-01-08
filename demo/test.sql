

-- 查看创建 show create procedure proInsertLog \G;
-- 查看函数 show create function func_name;

call proBuy(5,6,7);


-- 测试执行
call proInsertLog(1,12356,'asdfasdf');
call proInsertLog(2,23456,'asdfasdf');
call proInsertLog(3,34567,'asdfasdf');
call proInsertLog(4,45678,'asdfasdf');
call proInsertLog(5,56789,'asdfasdf');

call proJsonDemo(1,'{"name":"张三","age":15,"info":{"city":"wuhu","address":"北京路","tel":"1350555555"}}');


select * from tabLog;
call proSelectLog(1);


call proUpdateLog(1,9987654);
call proSelectLog(1);

call proDeleteLog(1);
call proSelectLog(1);

call proDeleteLog(2);
call proSelectLog(1);







-- 单独修改某一个过程
DELIMITER $$
DROP PROCEDURE IF EXISTS `proSelectLog`;
CREATE DEFINER=`root`@`localhost` PROCEDURE `proSelectLog`(IN uid int)
BEGIN
    select * from tabLog where `logUserID` = uid;
END;$$
DELIMITER ;

-- 单独修改某一个过程
DELIMITER $$
DROP PROCEDURE IF EXISTS `proDeleteLog`;
CREATE DEFINER=`root`@`localhost` PROCEDURE `proDeleteLog`(IN userID int)
BEGIN
    delete from tabLog where `logUserID` = userID;
    select found_rows() as rows,row_count() as ups;
END;$$
DELIMITER ;


