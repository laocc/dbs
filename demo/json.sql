
-- 设置界定符
DELIMITER $$

DROP PROCEDURE IF EXISTS `proJsonDemo`;

-- JSON操作
CREATE DEFINER=`root`@`localhost` PROCEDURE `proJsonDemo`(IN userID int,IN userInfo varchar(200))
proJson:BEGIN

    if !json_valid(userInfo) then
        select "JSON不合法" as error;
        LEAVE proJson;
    end if;

    set @json=json_extract(userInfo,'$');

    set @name=json_extract(userInfo,'$.name');

    set @name2=json_unquote(@name);

    select @name,@name2,@json;

END;
$$

DELIMITER ;
