


/**

mysql> source /mnt/hgfs/git/github/laocc/mysql/demo/PROCEDURE.sql;


 */

set global log_bin_trust_function_creators=on;

-- 设置界定符
DELIMITER $$

-- 删除原表
DROP TABLE IF EXISTS `tabLog`;

-- 删除原存储过程
DROP PROCEDURE IF EXISTS `proInsertLog`;
DROP PROCEDURE IF EXISTS `proDeleteLog`;
DROP PROCEDURE IF EXISTS `proUpdateLog`;
DROP PROCEDURE IF EXISTS `proSelectLog`;
DROP PROCEDURE IF EXISTS `proBuy`;
DROP PROCEDURE IF EXISTS `proJsonDemo`;
$$

-- 创建表
CREATE TABLE IF NOT EXISTS `tabLog` (
	logID int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
	logUserID int UNSIGNED COMMENT '用户ID',
	logTime int UNSIGNED COMMENT '操作时间',
	lotContent text COMMENT '内容',
primary key(logID),key logUserID (logUserID))
ENGINE=Innodb DEFAULT CHARSET=utf8mb4 COMMENT='操作日志' $$

-- 创建函数
DROP FUNCTION IF EXISTS `funStrRand`$$
CREATE FUNCTION `funStrRand`(lens int) RETURNS varchar(255)
BEGIN
  set @str  = '据新华社电日前，中共中央政治局常委、国务院总理李克强主持国务院专题讲座，讨论加快发展先进制造与3D打印等问题。李克强指出，推动中国制造由大变强，要紧紧依靠深化改革和创新驱动，加快实施“中国制造2025”和“互联网+”行动，努力克服创新能力弱、产品附加值不高、管理和销售服务落后、资源环境约束加剧等问题，突破发达国家先进技术和发展中国家低成本竞争的双重挤压，通过创业创新助推产业和技术变革，在转变发展方式中培育中国制造竞争新优势，促进经济中高速增长，迈向中高端水平。李克强强调，提高中国制造整体竞争力，关键要用大众创业、万众创新激发亿万人的创造活力。深入推进简政放权、放管结合、优化服务改革，完善政府监管方式，营造破束缚、汇众智、促创新和维护公平的良好环境。要以众创、众包、众筹、众扶等推动企业包括大企业生产模式和组织方式变革，通过体制创新增强聚集各类创新资源的能力和内生创新活力，让有界的传统企业变成开放式、协同式创新平台，让广大热衷创新创造的创客和极客大展身手，使“双创”成为新动能，让更多有生命力的前沿技术和新兴产业集群蓬勃发展，共同铸就中国制造业新辉煌。现场“不用赶时间”总理请院士敞开讲8月21日下午，国务院第一会议室变成了一间临时讲座场所。主讲者是一位70岁的白发院士，而“听众”则是国务院总理、副总理、国务委员，以及各部部长、央企、金融机构的负责人。第一会议室平日里是召开国务院常务会议、讨论部署重大政策的地方。今天，它变成本届政府首次“专题讲座”的课堂。第一次专题讲座题目很“潮”国务院第一次专题讲座的题目很“潮”：先进制造与3D打印。中国工程院院士、“高档数控机床与基础制造装备”重大专项技术总师、西安交通大学机械工程学院院长卢秉恒受邀主讲。李克强开宗明义表示，当今技术革命对经济发展、推动经济升级起着极为关键的作用，我们正在倡导大众创业万众创新，也是用创新的手段来推动创业。新事物层出不穷，像3D打印已经成为国际上一个新的技术潮流，实际上从实验室研究到开始应用已经很长时间了。“今天这个专题讲座，特意请国务院各位领导、各位部长们和央企、金融机构的负责人来听听讲解，以增加我们新的知识，同时也能启发我们的创新思维。”事实上，这场专题讲座本身安排得就颇有新颖之处。在会场外迎接“听众”的，除了往常的工作人员外，还有一个闪烁着“笑脸”的智能机器人。国务院第一会议室内历来“惜时如金”。常务会议上，部长们的汇报时间原则上不超过10分钟。即便召开各类座谈会，时间控制不好的发言者经常也会收到工作人员的纸条提示。卢秉恒院士在这方面显然相当严谨。他对讲稿中的一些段落有时仅举其要，PPT演示中的一些要点也只是一语带过。李克强总理很快注意到此点。他马上插话道：“卢院士，您敞开讲，没关系，不用赶时间。我们今天主要就是听您讲，不用节省时间。”“3D打印展现了全民创新的通途”卢秉恒分析了我国制造业发展现状，尤其指出其中存在的问题，如高端装备制造的核心技术尚待加强、机器人和数控机床等底层装备的自动化信息化不够、企业创新能力差、协调发展不充分等。他重点讲解了3D打印。从制造方式来说，铸锻焊在制造过程中重量基本不变，属于“等材制造”，已有3000年历史；随着电动机发明，车铣刨磨机床出现，通过材料的切削去除达到设计形状，称为“减材制造”，已有300年历史；而以3D打印为代表的“增材制造”，1984年提出，1986年实现样机，才30年时间，是极有前景的制造技术。权威机构的报告列出了对人类生活具有颠覆性影响的12项技术，3D打印排第9位，列新材料和页岩气之前。在介绍了光固化、选择性激光烧结/熔融、熔融堆积等几种当前主流技术后，卢院士重点讲到了3D打印所带来的革命性改变。2014年，GE公司研发的飞机发动机喷嘴，把20个零件做成了一个零件，材料成本大幅度减少，还节省燃油15%。这等于一代发动机的概念。而每开发一代发动机要上亿欧元，如今一个喷嘴就解决了。还是这家公司，曾在网上发布了一条消息，挑战3D打印，将飞机的一个零部件让创客设计。收集的700多个方案中，第一名只用了原始结构的1/6的重量就完成了全部测试。设计者是一个19岁的年轻人，方案超过了GE公司里的资深专家。“3D打印展现了全民创新的通途。”卢秉恒笃定地说。增材制造的前景是“创材”，即按照材料基因组，研制出超高强度、超高耐温、超高韧性、超高抗蚀的新材料。目前3D打印已制造出了耐温3315摄氏度的合金，用于“龙飞船2号”，大幅增强了飞船推力。进而可以从“创材”到“创生”，即打印细胞制造器官，甚至把基因打印在细胞里实现基因变异。卢院士介绍，我国的3D打印相比国外，研究起步并不晚，技术并不落后，某些方面还处于领先地位，但产业的发展太慢，企业规模不足。上述颠覆性技术都是2013年、2014年刚刚出来的，可见这一领域现在处于技术井喷期，企业处于跑马圈地期。我们国家应该及时拿出自己的应对策略来。总理追问“互联网+”与“+互联网”中国工程院研讨过制造强国的一些指标：规模、质量、结构优化、可持续发展，等等。2012年，中国的综合指标为814，落后于美国(1559)、日本(1213)、德国(1107)等国。卢秉恒比较了中、德、美三国，认为德国的工业优势在于质量过硬、基础雄厚、工艺严谨；美国的优势在于社会创新、高科技研发、集全球资源与精英；中国的优势则在于有比较完整的工业体系、内需市场巨大、人力资源丰富。他建议，中国目前需要在工业20、30方面补课，即质量优先、机器人和高档数控机床，同时推进实施“中国制造2025”，大力发展工业40。国家层面的协同创新，卢院士比较了德国的弗朗霍夫研究院和美国的制造创新网络计划。前者是德国工业创新的策源地，后者是美国为了消除基础研究与产业化技术之间的鸿沟。他认为，德国的模式偏重“制造+互联网”，而美国则偏重“互联网+制造”。听到这里，李克强总理马上追问道：“‘互联网+制造’和‘制造+互联网’究竟有什么不同？请您详细阐述一下。”卢秉恒进行了解释，并建议对美国和德国的优势要素都要合理地进行吸收。他尤其强调，要用工业互联网构成高技术的服务业，构建新机制的创新体系，驱动知识信息的流动。企业的资源是有限的，用工业互联网把全国的、全社会的，乃至全球的人才、资源都集中到一块，达到优化整合——这就是智能制造的精华。卢院士说，必须突破围墙，让知识充分流动起来，补足中国制造业开发能力弱的短板，这就是互联网带动制造业发展的真谛，也是最大的效益所在。他设想了未来制造业可能的前景：一半以上的制造为个性化定制，一半以上的价值由创新设计体现，一半以上的企业业务由众包完成，一半以上的创新研发由极客、创客实现。这场为时不长的专题讲座，现场近百名“听众”先后报以4次热烈的掌声。卢秉恒最后起身致谢时，坐在他正对面的李克强总理连连摊手示意这位院士：“您请坐，您请坐！”李克强结语说：“组织这次专题讲座的目的，是希望大家多了解新事物、了解新情况，在这一过程中不仅学习新技术，更要吸收新理念，并且要和政府职能结合起来创新思考。希望各部门今后也可以多组织这样的专题讲座。';
  set @iLen = FLOOR((0.5-rand())*(lens/2))+lens;
  set @star = FLOOR(rand()*length(@str)/3)-@iLen;
  set @value= SUBSTRING(@str,@star,@iLen);
  RETURN @value;
END $$


/**
创建存储过程
后面括号里的参数是执行此过程的出入参
*/

-- 增
CREATE DEFINER=`root`@`localhost` PROCEDURE `proInsertLog`(IN logUserID int,IN logTime int,IN lotContent text)
BEGIN
   insert into tabLog (logUserID,logTime,lotContent) values (logUserID,logTime,lotContent);
   select LAST_INSERT_ID() as logID;
END;$$


-- 删
CREATE DEFINER=`root`@`localhost` PROCEDURE `proDeleteLog`(IN userID int)
BEGIN
    delete from tabLog where `logUserID` = userID;
    select found_rows() as rows,row_count() as ups;
END;$$

-- 改
CREATE DEFINER=`root`@`localhost` PROCEDURE `proUpdateLog`(IN userID int,IN lTime int)
BEGIN
    update tabLog set `logTime`=lTime where `logUserID` = userID;
    select found_rows() as rows,row_count() as ups;
END;$$


-- 查
CREATE DEFINER=`root`@`localhost` PROCEDURE `proSelectLog`(IN userID int,IN skipRow int,IN limitRow int)
BEGIN
    select * from tabLog where `logUserID` = userID order by logID desc limit skipRow,limitRow;
END;
$$


-- 应用示例，这里只是示例相关语法，不必关心为什么这么转来转去
CREATE DEFINER=`root`@`localhost` PROCEDURE `proBuy`(IN userID int,IN goodsID int,IN buyNumber int)

buyIN:BEGIN
    -- 局部变量，类似private
    DECLARE i INT UNSIGNED DEFAULT 0;
    DECLARE j INT UNSIGNED DEFAULT 0;

    -- 全局变量，类似public，在多个begin/end之间共享
    SET @lenTitle   =6;
    set @error      ='';
    set @result     =0;


    -- 删除临时表，最好先删除，如果不删除，临时表一直在内存中，多线程读取时可能会存在内存污染
    DROP TABLE IF EXISTS `tmp_table`;
    DROP TABLE IF EXISTS `tmp_log`;

    -- 创建临时内存表
    CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_table` (testTitle varchar(10),testTitLen int(10));
    CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_log` (logUserID int(10),lotContent varchar(100),logTime int(11));

    -- 创建临时数据
    set @title = funStrRand(@lenTitle);
    insert INTO tmp_table(testTitle,testTitLen) values (@title,length(@title));


    -- 从表里取变量，要用into到另一个变量，这里要注意，取回的数据只能是一行
    select logID,logTime into @logID,@logTime from tabLog where `logUserID` = userID limit 1;

    -- 相等判断，和sql普通句一样，不要用==
    if @logID = 3 then
        set @error  =   concat('用户(', userID ,')登录时间(', @logTime ,')过去的太久了'); -- 字符串拼接
        select @error,@result;
        LEAVE buyIN;
    end if;

    if buyNumber > 50 then
        set @error  =   'buyNumber太大了，数量不够';
        -- 跳出之前要select结果，否则就没有数据返回了
        select @error,@result;
        LEAVE buyIN;
    end if;

    -- loop方式循环添加
    loopInsert:LOOP
        set i=i+1;
        if i>100 then
            LEAVE loopInsert;
        end if;

        set @title = funStrRand(@lenTitle);
        set @logTime = FLOOR(1000 + RAND()*1000);
        insert INTO tmp_log values (i,@title,@logTime);
    END LOOP loopInsert;

    -- while 方式循环添加
    while i<200 do
        set i=i+1;
        set @title = funStrRand(@lenTitle);
        set @logTime = FLOOR(1000 + RAND()*1000);
        insert INTO tmp_log values (i,@title,@logTime);
    end while;

    -- 只添加一条数据
    insert into tabLog (logUserID,logTime,lotContent) values (userID,goodsID,@title);

    -- 从临时表一次性复制进最终数据表中，如果临时表结构顺序和数据表完全相同，select 后可以直接用*号
    insert into tabLog (logUserID,logTime,lotContent) (select logUserID,logTime,lotContent from tmp_log);

    -- 也可以将插入字段的顺序排列和临时表相同，也可以直接用*号
    insert into tabLog (logUserID,lotContent,logTime) (select * from tmp_log);

    -- 这里只能读取最后一个ID，若要读更多的ID，需再次执行查询
    set @result = LAST_INSERT_ID();

    -- 如果需要返回某个结果，可以直接select
    select * from tmp_log;

    -- 建议一个存储过程中不要有多个select结果，这里只是为了演示
    select @error,@result;

    -- 清空临时表，清不清都可以，但表要删除掉
    delete from tmp_table;
    delete from tmp_log;
    DROP TEMPORARY TABLE IF EXISTS tmp_table;
    DROP TEMPORARY TABLE IF EXISTS tmp_log;

END;



-- JSON操作
CREATE DEFINER=`root`@`localhost` PROCEDURE `proJsonDemo`(IN userID int,IN userInfo varchar(200))
proJson:BEGIN

    if !json_valid(userInfo) then
        select "JSON不合法" as error;
        LEAVE proJson;
    end if;

    -- 解析全部json
    set @json=json_extract(userInfo,'$');

    -- 只提取其中一个字段
    set @name=json_extract(userInfo,'$.name');

    -- 提取更深的值
    set @info=json_extract(userInfo,'$.info.city');

    -- 去除双引号
    set @name2=json_unquote(@name);

    select @name,@name2,@json,@info;

END;



$$


-- 查看所有存储过程和函数
select db,name,type from mysql.proc where db = 'dbSeek' and (`type` = 'PROCEDURE' or `type` = 'FUNCTION');

$$


-- 恢复定符
DELIMITER ;



-- 测试执行
call proInsertLog(1,12356,'asdfasdf');
call proInsertLog(2,23456,'asdfasdf');
call proInsertLog(3,34567,'asdfasdf');
call proInsertLog(4,45678,'asdfasdf');
call proInsertLog(5,56789,'asdfasdf');

select * from tabLog;

