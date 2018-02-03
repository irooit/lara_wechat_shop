## 基于Laravel与ElementUI的项目

### 安装项目
1. 安装php依赖
    ```bash
    composer install
    ```
    
2. 安装node依赖
    ```bash
    npm install
    ```
    > 若执行 `npm install` 时出现 `Can't find Python executable "python"` 的问题, 执行以下 `npm install -g node-gyp` 即可
    
3. 环境配置

    `.env` 环境配置文件, 配置数据库连接及数据库名, 手动创键对应的数据库
    
4. 执行数据库迁移命令
    ```bash
    php artisan migrate
    ```
    
5. 执行数据填充命令, 填充初始数据

