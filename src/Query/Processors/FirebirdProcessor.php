<?php

namespace Firebird\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;

class FirebirdProcessor extends Processor
{
    /**
     * Process an "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        // The pdo_firebird driver does not support `lastInsertId()`. Perform
        // the insert operation in a way that returns the id.

        $result = $query->getConnection()->selectFromWriteConnection($sql, $values)[0];

        $sequence = $sequence ?: 'id';

        $id = is_object($result) ? $result->{$sequence} : $result[$sequence];

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Process the results of an indexes query.
     *
     * Megabit: one row per index column (see the schema grammar's
     * compileIndexes). Names are lower-cased, as Laravel's Schema::hasIndex
     * compares them in lower case; columns keep their case.
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array{name: string, columns: list<string>, type: string|null, unique: bool, primary: bool}>
     */
    public function processIndexes($results)
    {
        $indexes = [];

        foreach ($results as $result) {
            $result = (object) $result;
            $name = strtolower($result->name);

            $indexes[$name] ??= [
                'name' => $name,
                'columns' => [],
                'type' => null,
                'unique' => (bool) $result->unique || in_array($result->constraint, ['PRIMARY KEY', 'UNIQUE'], true),
                'primary' => $result->constraint === 'PRIMARY KEY',
            ];

            $indexes[$name]['columns'][] = $result->column;
        }

        return array_values($indexes);
    }
}
