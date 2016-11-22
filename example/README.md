

# Mysql
- 测度mysql前，要先将dbTest.sql导入数据库
- 将创建：
    - 数据库：dbTest
    - 用户：useTest
    - 密码：pwdTest
    - 数据：100条测试数据，测试过程中还会增加其他数据
- 测试结束后记得删除这个库和用户
    

# MongoDB
测试结束，需要在客户端删除dbTest库
```
# /usr/local/mongodb/bin/mongo
> use dbTest
> db.removeUser("userName")
> db.dropDatabase()
```

    
# Memcache,Memcached

# Redis

# Yac

# Apcu


    
    


