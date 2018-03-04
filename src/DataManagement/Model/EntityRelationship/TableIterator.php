<?php
/**
 * Created by PhpStorm.
 * User: Bogdans
 * Date: 04/03/2018
 * Time: 15:12
 */

namespace DataManagement\Model\EntityRelationship;


use DataManagement\Storage\FileStorage;

class TableIterator
{
    private $table;
    private $rowSize;
    private $rowFormat;
    private $rowWriteFormat;
    private $systemRowSize;
    private $systemRowFormat;
    private $systemRowWriteFormat;

    public function __construct(Table $table)
    {
        $this->table = $table;
        $this->rowSize = array_sum(array_column($table->structure(), 'size'));
        $this->rowFormat = implode('/', array_map(function($column, $formatCode) {
            return $formatCode . $column['name'];
        }, $table->structure(), array_map([TableHelper::class, 'getFormatCode'], $table->structure())));
        $this->rowWriteFormat = implode('', array_map([TableHelper::class, 'getFormatCode'], $table->structure()));
        $this->systemRowSize = 1;
        $this->systemRowFormat = 'C1state';
        $this->systemRowWriteFormat = 'C';
    }

    public function table()
    {
        return $this->table;
    }

    public function skip(int $amountOfRecords)
    {
        fseek($this->table->storage()->handle(), $amountOfRecords * ($this->rowSize + $this->systemRowSize), SEEK_CUR);
    }

    public function rewind(int $amountOfRecords)
    {
        fseek($this->table->storage()->handle(), -1 * $amountOfRecords * ($this->rowSize + $this->systemRowSize), SEEK_CUR);
    }

    public function jump(int $positionOfRecord)
    {
        fseek($this->table->storage()->handle(), ($positionOfRecord - 1) * ($this->rowSize + $this->systemRowSize), SEEK_SET);
    }

    /**
     * @param int $status
     * @return string
     */
    private function packSystemRecord(int $status) : string
    {
        return pack($this->systemRowWriteFormat, $status);
    }

    /**
     * @param array $record
     * @return string
     * @throws \Exception
     */
    private function packRecord(array $record) : string
    {
        $recordForPacking = [];

        foreach($this->table->structure() as $column) {
            if (array_key_exists($column['name'], $record) !== true) {
                throw new \Exception(sprintf('missing column %s in the record', $column['name']));
            }
            $recordForPacking[] = $record[$column['name']];
        }
        return pack($this->rowWriteFormat, ...$recordForPacking);
    }

    /**
     * @param string $binaryRecord
     * @return array
     */
    private function unpackSystemRecord(string $binaryRecord)
    {
        $state = substr($binaryRecord, 0, $this->systemRowSize);
        return unpack($this->systemRowFormat, $state);
    }

    /**
     * @param string $binaryRecord
     * @return array
     */
    private function unpackRecord(string $binaryRecord)
    {
        $record = substr($binaryRecord, $this->systemRowSize, $this->rowSize);
        return unpack($this->rowFormat, $record);
    }

    /**
     * @param array $record
     * @throws \Exception
     */
    public function create(array $record)
    {
        $binaryRecord = '';
        $binaryRecord .= $this->packSystemRecord(Table::INTERNAL_ROW_STATE_ACTIVE);
        $binaryRecord .= $this->packRecord($record);
        fwrite($this->table->storage()->handle(), $binaryRecord, $this->systemRowSize + $this->rowSize);
    }

    /**
     * @return array|null
     * @throws \Exception
     */
    public function read()
    {
        $binaryRecord = fread($this->table->storage()->handle(), $this->systemRowSize + $this->rowSize);
        if ($binaryRecord === '') {
            return null;
        }
        $systemRecord = $this->unpackSystemRecord($binaryRecord);
        if ($systemRecord['state'] === Table::INTERNAL_ROW_STATE_DELETE) {
            return null;
        }
        return $this->unpackRecord($binaryRecord);
    }

    /**
     * @param array $updates
     * @throws \Exception
     */
    public function update(array $updates)
    {
        foreach($updates as $name => $update) {
            $column = TableHelper::getColumnByName($this->table, $name);
            $sizeUntilColumn = TableHelper::getSizeUntilColumnByName($this->table, $name) + $this->systemRowSize;
            $columnPacked = pack(TableHelper::getFormatCode($column), $update);
            // jump to column beginning
            fseek($this->table->storage()->handle(), +$sizeUntilColumn, SEEK_CUR );
            // update the column
            fwrite($this->table->storage()->handle(), $columnPacked, $column['size']);
            // jump to the next row
            fseek($this->table->storage()->handle(), -$sizeUntilColumn-$column['size'], SEEK_CUR );
        }
    }

    public function delete()
    {
        $binarySystemRecord = $this->packSystemRecord(Table::INTERNAL_ROW_STATE_DELETE);
        fwrite($this->table->storage()->handle(), $binarySystemRecord, $this->systemRowSize);
        fseek($this->table->storage()->handle(), -$this->systemRowSize, SEEK_CUR );
    }

    /**
     * @return bool
     */
    public function endOfTable()
    {
        return feof($this->table->storage()->handle());
    }
}