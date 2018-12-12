<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/7/20
 * Time: 上午11:24
 */

namespace Lib\Mysqli;

use Lib\Mysqli\Exceptions\ConnectFail;
use Lib\Mysqli\Exceptions\JoinFail;
use Lib\Mysqli\Exceptions\Option;
use Lib\Mysqli\Exceptions\OrderByFail;
use Lib\Mysqli\Exceptions\PrepareQueryFail;
use Lib\Mysqli\Exceptions\WhereParserFail;
use Swoole\Coroutine\MySQL as CoroutineMySQL;
use Swoole\Coroutine\MySQL\Statement;

class Mysqli
{
    private $config;//数据库配置项
    private $coroutineMysqlClient;//swoole 协程MYSQL客户端
    /*
     * 以下为ORM构造配置项
     */
    private $where = [];
    private $join = [];
    private $orderBy = [];
    private $groupBy = [];
    private $bindParams = [];
    private $query = null;
    private $queryOptions = [];
    private $having = [];
    private $updateColumns = [];
    private $affectRows = 0;
    private $totalCount = 0;
    private $tableName;
    private $forUpdate = false;
    private $lockInShareMode = false;
    private $isFetchSql = false;
    private $queryAllowOptions = [ 'ALL', 'DISTINCT', 'DISTINCTROW', 'HIGH_PRIORITY', 'STRAIGHT_JOIN', 'SQL_SMALL_RESULT',
        'SQL_BIG_RESULT', 'SQL_BUFFER_RESULT', 'SQL_CACHE', 'SQL_NO_CACHE', 'SQL_CALC_FOUND_ROWS',
        'LOW_PRIORITY', 'IGNORE', 'QUICK' ];

    /*
     * 子查询配置
     */
    private $alias;

    /*
     * 以下为错误或者debug信息
     */
    private $stmtError;
    private $stmtErrno;
    private $lastQuery;
    private $traceEnabled = false;//是否开启调用追踪
    private $trace = [];//追踪调用记录
    private $traceQueryStartTime = null;//语句开始执行时间
    private $lastInsertId;

    /*
     * 事务配置项
     */
    private $startTransaction = false;

    /*
     * fetch_mode模式时
     */

    private $lastStatement;

    function __construct(Config $config)
    {
        $this->config = $config;
        if (!$this->config->isSubQuery()) {
            $this->coroutineMysqlClient = new CoroutineMySQL();
        }
    }

    function setTableName(string $tableName):void
    {
        $this->tableName = $tableName;
    }

    function selectForUpdate(bool $bool):Mysqli
    {
        $this->forUpdate = $bool;
        return $this;
    }

    function lockInShare(bool $bool):Mysqli
    {
        $this->lockInShareMode = $bool;
        return $this;
    }

    /**
     * 链接数据库
     * @return true 链接成功返回 true
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     */
    public function connect()
    {
        if ($this->coroutineMysqlClient->connected) {
            return true;
        } else {
            try {
                $ret = $this->coroutineMysqlClient->connect($this->config->toArray());
                if ($ret) {
                    return true;
                } else {
                    $errno = $this->coroutineMysqlClient->connect_errno;
                    $error = $this->coroutineMysqlClient->connect_error;
                    throw new ConnectFail("connect to {$this->config->getHost()}@{$this->config->getUser()} at port {$this->config->getPort()} fail: {$errno} {$error}");
                }
            } catch (\Throwable $throwable) {
                throw $throwable;
            }
        }
    }

    /**
     * 断开数据库链接
     */
    function disconnect()
    {
        $this->coroutineMysqlClient->close();
    }

    /*
     * 获取协程客户端
     */
    function getMysqlClient(): CoroutineMySQL
    {
        $this->connect();
        return $this->coroutineMysqlClient;
    }

    /*
     * 重置数据库状态
     */
    public function resetDbStatus()
    {
        if ($this->traceEnabled)
            $this->trace[] = array( $this->lastQuery, (microtime(true) - $this->traceQueryStartTime), $this->traceGetCaller() );

        $this->where = [];
        $this->join = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->bindParams = [];
        $this->query = null;
        $this->queryOptions = [];
        $this->having = [];
        $this->updateColumns = [];
//        $this->tableName = '';
        $this->forUpdate = false;
        $this->lockInShareMode = false;
        $this->isFetchSql = false;
        // $this->affectRows = 0;  // 此字段不需要重置
        // $this->totalCount = 0;  // 此字段不需要重置
    }

    /**
     * 开启查询跟踪
     */
    function startTrace()
    {
        $this->traceEnabled = true;
        $this->trace = [];
    }

    /**
     * 结束查询跟踪并返回结果
     * @return array
     */
    function endTrace()
    {
        $this->traceEnabled = true;
        $res = $this->trace;
        $this->trace = [];
        return $res;
    }

    /**
     * 执行原始查询语句
     * @param string $query 需要执行的语句
     * @param array $bindParams 如使用参数绑定语法 请传入本参数
     * @return mixed 被执行语句的查询结果
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail 如判断传入语句不合法请捕获此错误
     */
    public function rawQuery($query, array $bindParams = [])
    {
        $this->bindParams = $bindParams;
        $this->query = $query;
        if ($this->isFetchSql) {
            $res = $this->replacePlaceHolders($this->query, $bindParams);
            $this->resetDbStatus();
        } else {
            $stmt = $this->prepareQuery();
            $res = $this->exec($stmt);
            $this->affectRows = $stmt->affected_rows;
            $this->stmtError = $stmt->error;
            $this->stmtErrno = $stmt->errno;
            $this->lastQuery = $this->replacePlaceHolders($this->query, $bindParams);
            $this->resetDbStatus();
        }
        return $res;
    }

    /**
     * 开启事务
     * @return bool 是否成功开启事务
     * @throws ConnectFail
     */
    public function startTransaction(): bool
    {
        if ($this->startTransaction) {
            return true;
        } else {
            $this->connect();
            $res = $this->coroutineMysqlClient->query('start transaction');
            if ($res) {
                $this->startTransaction = true;
            }
            return $res;
        }
    }

    /**
     * 提交事务
     * @return bool 是否成功提交事务
     * @throws ConnectFail
     */
    public function commit(): bool
    {
        if ($this->startTransaction) {
            $this->connect();
            $res = $this->coroutineMysqlClient->query('commit');
            if ($res) {
                $this->startTransaction = false;
            }
            return $res;
        } else {
            return true;
        }
    }

    /**
     * 回滚事务
     * @param bool $commit
     * @return array|bool
     * @throws ConnectFail
     */
    public function rollback($commit = true)
    {
        if ($this->startTransaction) {
            $this->connect();
            $res = $this->coroutineMysqlClient->query('rollback');
            if ($res && $commit) {
                $res = $this->commit();
                if ($res) {
                    $this->startTransaction = false;
                }
                return $res;
            } else {
                return $res;
            }
        } else {
            return true;
        }
    }

    /**
     * 添加一个WHERE条件
     * @param string $whereProp 字段名
     * @param string $whereValue 字段值
     * @param string $operator 字段操作
     * @param string $cond 多个where的逻辑关系
     * @return Mysqli
     */
    public function where($whereProp, $whereValue = 'DBNULL', $operator = '=', $cond = 'AND'): Mysqli
    {
        if (is_array($whereValue) && ($key = key($whereValue)) != "0") {
            $operator = $key;
            $whereValue = $whereValue[$key];
        }
        if (count($this->where) == 0) {
            $cond = '';
        }
        $this->where[] = array( $cond, $whereProp, $operator, $whereValue );
        return $this;
    }

    /**
     * SELECT 查询数据
     * @param string $tableName 需要查询的表名称
     * @param null|integer $numRows 需要返回的行数
     * @param string $columns 需要返回的字段
     * @return $this|mixed
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail
     */
    public function get($numRows = null, $columns = '*')
    {
        if(!$this->tableName) return null;
        empty($columns) && $columns = '*';
        $column = is_array($columns) ? implode(', ', $columns) : $columns;
        $this->query = 'SELECT ' . implode(' ', $this->queryOptions) . ' ' .
            $column . ' FROM ' . $this->tableName;
        $stmt = $this->buildQuery($numRows);

        if ($this->config->isSubQuery()) {
            return $this;
        }
        if ($this->isFetchSql) {
            return $this->replacePlaceHolders($this->query, $this->bindParams);
        }
        $res = $this->exec($stmt);
        $this->stmtError = $stmt->error;
        $this->stmtErrno = $stmt->errno;
        $this->affectRows = $stmt->affected_rows;
        $this->resetDbStatus();
        return $res;
    }

    /**
     * SELECT LIMIT 1 查询单条数据
     * @param string $tableName 需要查询的表名称
     * @param string $columns 需要返回的字段
     * @return Mysqli|mixed|null
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail
     */
    public function getOne($columns = '*')
    {
        $res = $this->get(1, $columns);
        if ($this->isFetchSql) {
            return $res;
        }
        if ($res instanceof Mysqli) {
            return $res;
        } elseif (is_array($res) && isset($res[0])) {
            return $res[0];
        } elseif ($res) {
            return $res;
        }
        return null;
    }

    /**
     * 获取某一个字段的值
     * @param string $tableName 需要查询的表名称
     * @param string $column 需要返回的字段
     * @param int $limit 限制返回的行数
     * @return array|null
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail
     */
    public function getValue($column, $limit = 1)
    {
        $res = $this->get($limit, "{$column} AS retval");
        if ($this->isFetchSql) {
            return $res;
        }
        if (!$res) {
            return null;
        }
        if ($limit == 1) {
            if (isset($res[0]["retval"])) {
                return $res[0]["retval"];
            }
            return null;
        }
        $newRes = Array();
        for ($i = 0; $i < $this->affectRows; $i++) {
            $newRes[] = $res[$i]['retval'];
        }
        return $newRes;
    }

    /**
     * 获取某一列的数据
     * @param string $tableName 需要查询的表名称
     * @param string $columnName 需要获取的列名称
     * @param null $limit 最多返回几条数据
     * @return array
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail
     */
    function getColumn($columnName, $limit = null)
    {
        $res = $this->get($limit, "{$columnName} AS retval");
        if ($this->isFetchSql) {
            return $res;
        }
        if (!$res) {
            return [];
        }
        return array_column($res, 'retval');
    }

    /**
     * 插入一行数据
     * @param string $tableName
     * @param array $insertData
     * @return bool|int
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    public function insert($insertData)
    {
        return $this->buildInsert($insertData, 'INSERT');
    }

    /**
     * REPLACE INSERT
     * @param array $insertData
     * @return bool|int|null
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    public function replace($insertData)
    {
        return $this->buildInsert($insertData, 'REPLACE');
    }

    /**
     * 聚合-计算总数
     * @param string $tableName 表名称
     * @param string|null $filedName 字段名称
     * @return mixed
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    public function count($filedName = null)
    {
        if (is_null($filedName)) {
            $filedName = '*';
        }
        $retval = $this->get(null, "COUNT({$filedName}) as retval");
        if ($this->isFetchSql || $retval instanceof Mysqli) {
            return $retval;
        }
        return $retval ? $retval[0]['retval'] : false;
    }

    /**
     * 聚合-求最大值
     * @param string $filedName 字段名称
     * @return mixed
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    public function max($filedName)
    {
        $retval = $this->get(null, "MAX({$filedName}) as retval");
        if ($this->isFetchSql || $retval instanceof Mysqli) {
            return $retval;
        }
        return $retval ? $retval[0]['retval'] : false;
    }

    /**
     * 聚合-求最小值
     * @param string $filedName 字段名称
     * @return mixed
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    public function min($filedName)
    {
        $retval = $this->get(null, "MIN({$filedName}) as retval");
        if ($this->isFetchSql || $retval instanceof Mysqli) {
            return $retval;
        }
        return $retval ? $retval[0]['retval'] : false;
    }

    /**
     * 聚合-计算和值
     * @param string $tableName 表名称
     * @param string $filedName 字段名称
     * @return mixed
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    public function sum($filedName)
    {
        $retval = $this->get(null, "SUM({$filedName}) as retval");
        if ($this->isFetchSql || $retval instanceof Mysqli) {
            return $retval;
        }
        return $retval ? $retval[0]['retval'] : false;
    }

    /**
     * 聚合-求平均值
     * @param string $tableName 表名称
     * @param string $filedName 字段名称
     * @return mixed
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    public function avg($filedName)
    {
        $retval = $this->get(null, "AVG({$filedName}) as retval");
        if ($this->isFetchSql || $retval instanceof Mysqli) {
            return $retval;
        }
        return $retval ? $retval[0]['retval'] : false;
    }

    /**
     * 删除数据
     * @param null|integer $numRows 限制删除的行数
     * @return bool|null
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail
     */
    public function delete($numRows = null)
    {
        if ($this->config->isSubQuery() || !$this->tableName) {
            return null;
        }
        if (count($this->join)) {
            $this->query = 'DELETE ' . preg_replace('/.* (.*)/', '$1', $this->tableName) . " FROM " . $this->tableName;
        } else {
            $this->query = 'DELETE FROM ' . $this->tableName;
        }
        $stmt = $this->buildQuery($numRows);
        if ($this->isFetchSql) {
            return $this->replacePlaceHolders($this->query, $this->bindParams);
        }
        $this->exec($stmt);
        $this->stmtError = $stmt->error;
        $this->stmtErrno = $stmt->errno;
        $this->resetDbStatus();

        return ($stmt->affected_rows > -1);    //	affected_rows returns 0 if nothing matched where statement, or required updating, -1 if error
    }


    /**
     * 更新数据
     * @param string $tableName 表名称
     * @param array $tableData 需要更新的数据
     * @param null|integer $numRows 限制更新的行数
     * @return mixed
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail
     */
    public function update($tableData, $numRows = null)
    {
        if ($this->config->isSubQuery() || !$this->tableName) {
            return null;
        }
        $this->query = "UPDATE " . $this->tableName;

        $stmt = $this->buildQuery($numRows, $tableData);
        if ($this->isFetchSql) {
            return $this->replacePlaceHolders($this->query, $this->bindParams);
        }
        $status = $this->exec($stmt);
        $this->resetDbStatus();
        $this->stmtError = $stmt->error;
        $this->stmtErrno = $stmt->errno;
        $this->affectRows = $stmt->affected_rows;

        return $status;
    }

    /**
     * 表是否存在
     * @return bool
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail
     */
    public function tableExists()
    {
        if (!$this->tableName) return false;
        $this->where('table_schema', $this->config->getDatabase());
        $this->where('table_name', $this->tableName, '=');
        $ret = $this->get('information_schema.tables');
        if (is_array($ret) && count($ret)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 执行字段自增操作
     * @param int|float $num
     * @return array
     */
    public function inc($num = 1)
    {
        return array("[I]" => "+" . $num);
    }

    /**
     * 执行字段自减操作
     * @param int|float $num
     * @return array
     * @author: eValor < master@evalor.cn >
     */
    public function dec($num = 1)
    {
        return array("[I]" => "-" . $num);
    }

    /**
     * 自增某个字段
     * @param string $tableName 表名称
     * @param string $filedName 操作的字段名称
     * @param int|float $num 操作数量
     * @return mixed
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail
     * @TODO set inc after lock some line
     */
    public function setInc($filedName, $num = 1)
    {
        return $this->update([ $filedName => $this->inc($num) ]);
    }

    /**
     * 自减某个字段
     * @param string $tableName 表名称
     * @param string $filedName 操作的字段名称
     * @param int|float $num 操作数量
     * @return mixed
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     * @throws PrepareQueryFail
     * @TODO set dec after lock some line
     */
    public function setDec($filedName, $num = 1)
    {
        return $this->update([ $filedName => $this->dec($num) ]);
    }

    /**
     * 获取即将执行的SQL语句
     * @param bool $fetch
     * @author: eValor < master@evalor.cn >
     * @return Mysqli
     */
    function fetchSql(bool $fetch = true)
    {
        $this->isFetchSql = $fetch;
        return $this;
    }

    /**
     * 查询结果总数
     * @return $this
     * @throws Option
     */
    public function withQueryCount()
    {
        $this->setQueryOption('SQL_CALC_FOUND_ROWS');
        return $this;
    }

    /**
     * 返回结果总数
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * 本次查询影响的行数
     * @return int
     */
    public function getAffectRows(): int
    {
        return $this->affectRows;
    }

    /**
     * 表连接查询
     * @param string $joinTable 被连接的表
     * @param string $joinCondition 连接条件
     * @param string $joinType 连接类型
     * @return $this
     * @throws JoinFail
     */
    public function join($joinTable, $joinCondition, $joinType = '')
    {
        $allowedTypes = array( 'LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER', 'NATURAL' );
        $joinType = strtoupper(trim($joinType));
        if ($joinType && !in_array($joinType, $allowedTypes)) {
            throw new JoinFail('Wrong JOIN type: ' . $joinType);
        }
        $this->join[] = Array( $joinType, $joinTable, $joinCondition );
        return $this;
    }

    /**
     * 设置额外查询参数
     * @param mixed $options 查询参数 可传入数组设置多个
     * @return $this
     * @throws Option
     */
    public function setQueryOption($options)
    {
        if (!is_array($options)) {
            $options = Array( $options );
        }
        foreach ($options as $option) {
            $option = strtoupper($option);
            if (!in_array($option, $this->queryAllowOptions)) {
                throw new Option('Wrong query option: ' . $option);
            } else {
                if (!in_array($option, $this->queryOptions)) {
                    $this->queryOptions[] = $option;
                }
            }
        }
        return $this;
    }

    public function getLastStatement(): ?Statement
    {
        return $this->lastStatement;
    }

    /**
     * 将全部的查询条件构建成语句并预处理
     * @param null|integer $numRows
     * @param mixed $tableData
     * @return null|Statement
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    private function buildQuery($numRows = null, $tableData = null)
    {
        $this->buildJoin();
        $this->buildInsertQuery($tableData);
        $this->buildCondition('WHERE', $this->where);
        $this->buildGroupBy();
        $this->buildCondition('HAVING', $this->having);
        $this->buildOrderBy();
        $this->buildLimit($numRows);
        $this->buildOnDuplicate($tableData);

        if ($this->forUpdate) {
            $this->query .= ' FOR UPDATE';
        }
        if ($this->lockInShareMode) {
            $this->query .= ' LOCK IN SHARE MODE';
        }

        $this->lastQuery = $this->replacePlaceHolders($this->query, $this->bindParams);

        if ($this->config->isSubQuery()) {
            return null;
        }
        // Prepare query
        $stmt = $this->prepareQuery();
        return $stmt;
    }

    /**
     * 执行预处理语句并返回结果
     * @param Statement $stmt
     * @return mixed
     * @author: eValor < master@evalor.cn >
     * @throws ConnectFail 链接失败时请外部捕获该异常进行处理
     */
    private function exec($stmt)
    {
        if (!$this->coroutineMysqlClient->connected) {
            $this->connect();
        }
        $this->lastStatement = $stmt;
        if (!empty($this->bindParams)) {
            $data = $this->bindParams;
        } else {
            $data = [];
        }
        $ret = $stmt->execute($data);
        if (in_array('SQL_CALC_FOUND_ROWS', $this->queryOptions)) {
            $hitCount = $this->getMysqlClient()->query('SELECT FOUND_ROWS() as count');
            $this->totalCount = $hitCount[0]['count'];
        }
        return $ret;
    }

    /**
     * 组装JOIN条件
     */
    private function buildJoin()
    {
        if (empty ($this->join))
            return;
        foreach ($this->join as $data) {
            list ($joinType, $joinTable, $joinCondition) = $data;

            if (is_object($joinTable))
                $joinStr = $this->buildPair("", $joinTable);
            else
                $joinStr = $joinTable;

            $this->query .= " " . $joinType . " JOIN " . $joinStr .
                (false !== stripos($joinCondition, 'using') ? " " : " on ")
                . $joinCondition;
            // Add join and query
            if (!empty($this->joinAnd) && isset($this->joinAnd[$joinStr])) {
                foreach ($this->joinAnd[$joinStr] as $join_and_cond) {
                    list ($concat, $varName, $operator, $val) = $join_and_cond;
                    $this->query .= " " . $concat . " " . $varName;
                    $this->conditionToSql($operator, $val);
                }
            }
        }
    }

    /**
     * 建立条件之间的关系
     * @param $operator
     * @param $value
     * @return string
     */
    private function buildPair($operator, $value)
    {
        if ($value instanceof Mysqli) {
            $subQuery = $value->getSubQuery();
            $this->bindParams($subQuery['params']);
            return " " . $operator . " (" . $subQuery['query'] . ") " . $subQuery['alias'];
        } else {
            $this->bindParam($value);
            return ' ' . $operator . ' ? ';
        }
    }

    /**
     * 将值加入待绑定的参数数组
     * @param mixed $value
     */
    private function bindParam($value)
    {
        array_push($this->bindParams, $value);
    }

    /**
     * 将多个值加入待绑定的参数数组
     * @param mixed $values
     */
    private function bindParams($values)
    {
        foreach ($values as $value) {
            $this->bindParam($value);
        }
    }

    /**
     * 条件转为SQL语句
     * @param $operator
     * @param $val
     */
    private function conditionToSql($operator, $val)
    {
        switch (strtolower($operator)) {
            case 'not in':
            case 'in':
                $comparison = ' ' . $operator . ' (';
                if (is_object($val)) {
                    $comparison .= $this->buildPair("", $val);
                } else {
                    foreach ($val as $v) {
                        $comparison .= ' ?,';
                        $this->bindParam($v);
                    }
                }
                $this->query .= rtrim($comparison, ',') . ' ) ';
                break;
            case 'not between':
            case 'between':
                $this->query .= " $operator ? AND ? ";
                $this->bindParams($val);
                break;
            case 'not exists':
            case 'exists':
                $this->query .= $operator . $this->buildPair("", $val);
                break;
            default:
                if (is_array($val))
                    $this->bindParams($val);
                else if ($val === null)
                    $this->query .= $operator . " NULL";
                else if ($val != 'DBNULL' || $val == '0')
                    $this->query .= $this->buildPair($operator, $val);
        }
    }

    /**
     * 获取子查询
     * @return array|null
     * @author: eValor < master@evalor.cn >
     */
    public function getSubQuery()
    {
        if (!$this->config->isSubQuery()) {
            return null;
        }
        $val = Array(
            'query'  => $this->query,
            'params' => $this->bindParams,
            'alias'  => $this->alias
        );
        $this->resetDbStatus();
        return $val;
    }

    /**
     * 创建子查询
     * @param string $subQueryAlias
     * @return Mysqli
     */
    public function subQuery($subQueryAlias = ""): Mysqli
    {
        $conf = new Config();
        $conf->setIsSubQuery(true);
        $conf->setAlias($subQueryAlias);
        return new self($conf);
    }

    /**
     * 构建插入语句
     * @param array $tableData 需要插入的数据
     * @throws \Exception
     */
    private function buildInsertQuery($tableData)
    {
        if (!is_array($tableData)) {
            return;
        }
        $isInsert = preg_match('/^[INSERT|REPLACE]/', $this->query);
        $dataColumns = array_keys($tableData);
        if ($isInsert) {
            if (isset ($dataColumns[0]))
                $this->query .= ' (`' . implode($dataColumns, '`, `') . '`) ';
            $this->query .= ' VALUES (';
        } else {
            $this->query .= " SET ";
        }
        $this->buildDataPairs($tableData, $dataColumns, $isInsert);
        if ($isInsert) {
            $this->query .= ')';
        }
    }

    /**
     * 构建插入语句的数据部分
     * @param array $tableData 插入的数据
     * @param array $tableColumns 插入的列名称
     * @param bool $isInsert Insert || OnDuplicate
     * @throws \Exception
     */
    private function buildDataPairs($tableData, $tableColumns, $isInsert)
    {
        foreach ($tableColumns as $column) {
            $value = $tableData[$column];

            if (!$isInsert) {
                if (strpos($column, '.') === false) {
                    $this->query .= "`" . $column . "` = ";
                } else {
                    $this->query .= str_replace('.', '.`', $column) . "` = ";
                }
            }
            // SubQuery value
            if ($value instanceof Mysqli) {
                $this->query .= $this->buildPair("", $value) . ", ";
                continue;
            }

            // Simple value
            if (!is_array($value)) {
                $this->bindParam($value);
                $this->query .= '?, ';
                continue;
            }

            // Function value
            $key = key($value);
            $val = $value[$key];
            switch ($key) {
                case '[I]':  // INC DEC
                    $this->query .= $column . $val . ", ";
                    break;
                case '[F]':  // IS FUNCTION
                    $this->query .= $val[0] . ", ";
                    if (!empty($val[1])) {
                        $this->bindParams($val[1]);
                    }
                    break;
                case '[N]':  // NOT
                    if ($val == null) {
                        $this->query .= "!" . $column . ", ";
                    } else {
                        $this->query .= "!" . $val . ", ";
                    }
                    break;
                default:
                    throw new \Exception("Wrong operation");
            }
        }
        $this->query = rtrim($this->query, ', ');
    }

    /**
     * WHERE/HAVING组装成语句
     * @param $operator
     * @param $conditions
     */
    private function buildCondition($operator, &$conditions)
    {
        if (empty($conditions)) {
            return;
        }

        $this->query .= ' ' . $operator;

        foreach ($conditions as $cond) {
            list ($concat, $varName, $operator, $val) = $cond;
            $this->query .= " " . $concat . " " . $varName;

            switch (strtolower($operator)) {
                case 'not in':
                case 'in':
                    $comparison = ' ' . $operator . ' (';
                    if (is_object($val)) {
                        $comparison .= $this->buildPair("", $val);
                    } else {
                        foreach ($val as $v) {
                            $comparison .= ' ?,';
                            $this->bindParam($v);
                        }
                    }
                    $this->query .= rtrim($comparison, ',') . ' ) ';
                    break;
                case 'not between':
                case 'between':
                    $this->query .= " $operator ? AND ? ";
                    $this->bindParams($val);
                    break;
                case 'not exists':
                case 'exists':
                    $this->query .= $operator . $this->buildPair("", $val);
                    break;
                default:
                    if (is_array($val)) {
                        $this->bindParams($val);
                    } elseif ($val === null) {
                        $this->query .= ' ' . $operator . " NULL";
                    } elseif ($val != 'DBNULL' || $val == '0') {
                        $this->query .= $this->buildPair($operator, $val);
                    }
            }
        }
    }

    /**
     * 构建分组查询
     */
    private function buildGroupBy()
    {
        if (empty($this->groupBy)) {
            return;
        }

        $this->query .= " GROUP BY ";

        foreach ($this->groupBy as $key => $value) {
            $this->query .= $value . ", ";
        }

        $this->query = rtrim($this->query, ', ') . " ";
    }

    /**
     * 构建字段排序
     */
    private function buildOrderBy()
    {
        if (empty($this->orderBy)) {
            return;
        }

        $this->query .= " ORDER BY ";
        foreach ($this->orderBy as $prop => $value) {
            if (strtolower(str_replace(" ", "", $prop)) == 'rand()') {
                $this->query .= "rand(), ";
            } else {
                $this->query .= $prop . " " . $value . ", ";
            }
        }

        $this->query = rtrim($this->query, ', ') . " ";
    }

    /**
     * 构建LIMIT限制
     * @param integer $numRows LIMIT 行数
     */
    private function buildLimit($numRows)
    {
        if (!isset($numRows)) {
            return;
        }

        if (is_array($numRows)) {
            $this->query .= ' LIMIT ' . (int)$numRows[0] . ', ' . (int)$numRows[1];
        } else {
            $this->query .= ' LIMIT ' . (int)$numRows;
        }
    }

    /**
     * BUILD INSERT INTO .. ON DUPLICATE
     * @param $tableData
     * @throws \Exception
     */
    private function buildOnDuplicate($tableData)
    {
        if (is_array($this->updateColumns) && !empty($this->updateColumns)) {
            $this->query .= " ON DUPLICATE KEY UPDATE ";
            if ($this->lastInsertId) {
                $this->query .= $this->lastInsertId . "=LAST_INSERT_ID (" . $this->lastInsertId . "), ";
            }

            foreach ($this->updateColumns as $key => $val) {
                // skip all params without a value
                if (is_numeric($key)) {
                    $this->updateColumns[$val] = '';
                    unset($this->updateColumns[$key]);
                } else {
                    $tableData[$key] = $val;
                }
            }
            $this->buildDataPairs($tableData, array_keys($this->updateColumns), false);
        }
    }

    /**
     * 替换参数绑定占位符
     * @param string $str
     * @param array $values
     * @return bool|string
     */
    private function replacePlaceHolders($str, $values)
    {
        $i = 0;
        $newStr = "";

        if (empty($values)) {
            return $str;
        }

        while ($pos = strpos($str, "?")) {
            $val = $values[$i++];
            if (is_object($val)) {
                $val = '[object]';
            }
            if ($val === null) {
                $val = 'NULL';
            }
            $newStr .= substr($str, 0, $pos) . "'" . $val . "'";
            $str = substr($str, $pos + 1);
        }
        $newStr .= $str;
        return $newStr;
    }

    /**
     * 生成预查询对象Statement
     * @return Statement
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    private function prepareQuery()
    {

        if (!$this->coroutineMysqlClient->connected) {
            $this->connect();
        }
        if ($this->traceEnabled)
            $this->traceQueryStartTime = microtime(true);
        $res = $this->coroutineMysqlClient->prepare($this->query);
        if ($res instanceof Statement) {
            return $res;
        }
        $error = $this->coroutineMysqlClient->error;
        $query = $this->query;
        $errno = $this->coroutineMysqlClient->errno;
        $this->resetDbStatus();
        throw new PrepareQueryFail(sprintf('%s query: %s', $error, $query), $errno);
    }

    /**
     * 构建插入语句
     * @param $tableName
     * @param $insertData
     * @param $operation
     * @return bool|int|null
     * @throws ConnectFail
     * @throws PrepareQueryFail
     */
    private function buildInsert($insertData, $operation)
    {
        if ($this->config->isSubQuery() || !$this->tableName) {
            return null;
        }
        $this->query = $operation . " " . implode(' ', $this->queryOptions) . " INTO " . $this->tableName;
        $stmt = $this->buildQuery(null, $insertData);
        $status = $this->exec($stmt);
        if ($this->isFetchSql) {
            return $this->lastQuery;
        }
        $this->stmtError = $stmt->error;
        $this->stmtErrno = $stmt->errno;
        $haveOnDuplicate = !empty ($this->updateColumns);
        $this->resetDbStatus();
        $this->affectRows = $stmt->affected_rows;
        if ($stmt->affected_rows < 1) {
            // in case of onDuplicate() usage, if no rows were inserted
            if ($status && $haveOnDuplicate) {
                return true;
            }
            return false;
        }
        if ($stmt->insert_id > 0) {
            return $stmt->insert_id;
        }
        return true;
    }

    /**
     * 获取最后插入的数据ID
     * @return int
     */
    public function getInsertId()
    {
        return $this->coroutineMysqlClient->insert_id;
    }

    /**
     * 获取最后一次查询的语句
     * @return mixed
     */
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    /**
     * 获取最后一次查询错误的内容
     * @return string
     */
    public function getLastError()
    {
        return trim($this->stmtError . " " . $this->coroutineMysqlClient->error);
    }

    /**
     * 获取最后一次查询错误的编号
     * @return mixed
     */
    public function getLastErrno()
    {
        return $this->stmtErrno;
    }

    /**
     * 字段排序
     * @param $orderByField
     * @param string $orderByDirection
     * @param null $customFieldsOrRegExp
     * @return $this
     * @throws OrderByFail
     */
    public function orderBy($orderByField, $orderByDirection = "DESC", $customFieldsOrRegExp = null)
    {
        $allowedDirection = Array( "ASC", "DESC" );
        $orderByDirection = strtoupper(trim($orderByDirection));
        $orderByField = preg_replace("/[^ -a-z0-9\.\(\),_`\*\'\"]+/i", '', $orderByField);

        $orderByField = preg_replace('/(\`)([`a-zA-Z0-9_]*\.)/', '\1' . '\2', $orderByField);

        if (empty($orderByDirection) || !in_array($orderByDirection, $allowedDirection)) {
            throw new OrderByFail('Wrong order direction: ' . $orderByDirection);
        }

        if (is_array($customFieldsOrRegExp)) {
            foreach ($customFieldsOrRegExp as $key => $value) {
                $customFieldsOrRegExp[$key] = preg_replace("/[^-a-z0-9\.\(\),_` ]+/i", '', $value);
            }
            $orderByField = 'FIELD (' . $orderByField . ', "' . implode('","', $customFieldsOrRegExp) . '")';
        } elseif (is_string($customFieldsOrRegExp)) {
            $orderByField = $orderByField . " REGEXP '" . $customFieldsOrRegExp . "'";
        } elseif ($customFieldsOrRegExp !== null) {
            throw new OrderByFail('Wrong custom field or Regular Expression: ' . $customFieldsOrRegExp);
        }

        $this->orderBy[$orderByField] = $orderByDirection;
        return $this;
    }

    public function having($havingProp, $havingValue = 'DBNULL', $operator = '=', $cond = 'AND')
    {
        // forkaround for an old operation api
        if (is_array($havingValue) && ($key = key($havingValue)) != "0") {
            $operator = $key;
            $havingValue = $havingValue[$key];
        }
        if (count($this->having) == 0) {
            $cond = '';
        }
        $this->having[] = array($cond, $havingProp, $operator, $havingValue);
        return $this;
    }

    /**
     * 字段分组
     * @param $groupByField
     * @return $this
     * @author: eValor < master@evalor.cn >
     */
    public function groupBy($groupByField)
    {
        $groupByField = preg_replace("/[^-a-z0-9\.\(\),_\* <>=!]+/i", '', $groupByField);

        $this->groupBy[] = $groupByField;
        return $this;
    }

    /**
     * 获取追踪调用
     * @return string
     */
    private function traceGetCaller()
    {
        $dd = debug_backtrace();
        $caller = next($dd);
        while (isset ($caller) && $caller["file"] == __FILE__)
            $caller = next($dd);
        return __CLASS__ . "->" . $caller["function"] . "() >>  file \"" .
            $caller["file"] . "\" line #" . $caller["line"] . " ";
    }

    /** 析构被调用时关闭当前链接并释放客户端对象 */
    function __destruct()
    {
        if (isset($this->coroutineMysqlClient) && $this->coroutineMysqlClient->connected) {
            $this->coroutineMysqlClient->close();
        }
        unset($this->coroutineMysqlClient);
    }
}