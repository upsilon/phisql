<?php
/**
 * Copyright 2013 Kimura Youichi <kim.upsilon@bucyou.net>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, version 2.0
 */

class Sql
{
    public function __construct($sql = null, array $args = array())
    {
        $this->_sql = $sql;
        $this->_args = $args;
    }

    public static function builder()
    {
        return new self();
    }

    private
        $_sql,
        $_args,
        $_rhs,
        $_sqlFinal,
        $_argsFinal;

    private function build()
    {
        // already built?
        if ($this->_sqlFinal !== null)
            return;

        // Build it
        $this->_sqlFinal = '';
        $this->_argsFinal = array();
        $this->_build($this->_sqlFinal, $this->_argsFinal, null);
    }

    /**
     * @return string
     */
    public function getSql()
    {
        $this->build();
        return $this->_sqlFinal;
    }

    /**
     * @return mixed[]
     */
    public function getArguments()
    {
        $this->build();
        return $this->_argsFinal;
    }

    /**
     * @param Sql|string $sql
     * @param mixed|mixed[] $args
     */
    public function append($sql, $args = array())
    {
        if ($sql instanceof self)
        {
            if ($this->_rhs !== null)
                $this->_rhs->append($sql);
            else
                $this->_rhs = $sql;

            return $this;
        }

        return $this->append(new self($sql, (array)$args));
    }

    private static function is(self $sql = null, $sqltype)
    {
        return $sql !== null && $sql->_sql !== null && stripos($sql->_sql, $sqltype) === 0;
    }

    private function _build(&$sql, &$args, self $lhs = null)
    {
        if ($this->_sql !== null && $this->_sql !== '')
        {
            // Add SQL to the string
            if (strlen($sql) > 0)
                $sql .= "\n";

            $_sql = self::processParams($this->_sql, $this->_args, $args);

            if (self::is($lhs, 'WHERE ') && self::is($this, 'WHERE '))
                $_sql = 'AND '.substr($_sql, 6);
            if (self::is($lhs, 'ORDER BY ') && self::is($this, 'ORDER BY '))
                $_sql = ', '.substr($_sql, 9);

            $sql .= $_sql;
        }

        // Now do rhs
        if ($this->_rhs !== null)
            $this->_rhs->_build($sql, $args, $this);
    }

    private static function processParams($_sql, array $args_src, array &$args_dest)
    {
        return preg_replace_callback('/(?<!@)@\w+/', function(array $matches) use ($_sql, $args_src, &$args_dest)
        {
            $param = substr($matches[0], 1);

            if (ctype_digit($param))
            {
                // Numbered parameter
                $paramIndex = (int)$param;
                if ($paramIndex < 0 || $paramIndex >= count($args_src))
                    throw new OutOfRangeException(sprintf('Parameter \'@%s\' specified but only %d parameters supplied (in `%s`)', $paramIndex, count($args_src), $_sql));

                $arg_val = $args_src[$paramIndex];
            }
            else
            {
                // Look for a property on one of the arguments with this name
                if (!isset($args_src[$param]))
                    throw new InvalidArgumentException(sprintf('Parameter \'@%s\' specified but none of the passed arguments have a property with this name (in \'%s\')', $param, $_sql));

                $arg_val = $args_src[$param];
            }

            // Expand collections to parameter lists
            if (is_array($arg_val))
            {
                $sb = '';
                foreach ($arg_val as $i)
                {
                    $sb .= (strlen($sb) === 0 ? '@' : ',@').count($args_dest);
                    $args_dest[] = $i;
                }
                return $sb;
            }
            else
            {
                $args_dest[] = $arg_val;
                return '@'.(count($args_dest) - 1);
            }
        }, $_sql);
    }

    public function where($sql, array $args = array())
    {
        return $this->append(new self('WHERE ('.$sql.')', $args));
    }

    /**
     * @param string|string[] $column
     */
    public function orderBy($column)
    {
        $columns = (array)$column;
        return $this->append(new self('ORDER BY '.implode(', ', $columns)));
    }

    /**
     * @param string|string[] $column
     */
    public function select($column)
    {
        $columns = (array)$column;
        return $this->append(new self('SELECT '.implode(', ', $columns)));
    }

    /**
     * @param string|string[] $table
     */
    public function from($table)
    {
        $tables = (array)$table;
        return $this->append(new self('FROM '.implode(', ', $tables)));
    }

    /**
     * @param string|string[] $column
     */
    public function groupBy($column)
    {
        $columns = (array)$column;
        return $this->append(new self('GROUP BY '.implode(', ', $columns)));
    }

    private function join($joinType, $table)
    {
        return new _SqlJoinClause($this->append(new self($joinType.$table)));
    }

    public function innerJoin($table)
    {
        return $this->join('INNER JOIN ', $table);
    }

    public function leftJoin($table)
    {
        return $this->join('LEFT JOIN ', $table);
    }
}

class _SqlJoinClause
{
    private $_sql;

    public function __construct(Sql $sql)
    {
        $this->_sql = $sql;
    }

    public function on($onClause, array $args = array())
    {
        return $this->_sql->append('ON '.$onClause, $args);
    }
}
// vim: et ts=4 sw=4 sts=4 fenc=utf-8
