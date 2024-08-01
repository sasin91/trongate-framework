<?php

// '/../vendor/autoload.php'
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Writer\Exception\WriterException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use OpenSpout\Writer\ODS\Writer;

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' .  DIRECTORY_SEPARATOR . 'autoload.php';

class Excel extends Trongate
{
    /**
     * Export data to Excel and send it to the browser for download or preview.
     *
     * @param iterable $data
     * @param string $output
     * @param array<string> $cells An array of cell names
     * @param string $format
     * @return void
     * @throws WriterException @see $writer->addRow(...)
     * @throws IOException @see $writer->addRow(...)
     */
    public function _export_data_to_browser(iterable $data, string $output, array $cells = [], string $format = 'xlsx'): void {
        $writer = $this->_writer($format);

        $writer->openToBrowser($output);

        $this->_add_rows($cells, $data, $writer);

        $writer->close();

        header('Content-Type', 'application/vnd.ms-excel');
    }

    /**
     * Export data to Excel to a local file.
     *
     * @param iterable $data
     * @param string $output
     * @param array<string> $cells An array of cell names
     * @param string $format
     * @return void
     * @throws WriterException @see $writer->addRow(...)
     * @throws IOException @see $writer->addRow(...)
     */
    public function _export_data_to_file(iterable $data, string $output, array $cells = [], string $format = 'xlsx'): void {
        $writer = $this->_writer($format);

        $writer->openToFile($output);

        $this->_add_rows($cells, $data, $writer);

        $writer->close();
    }

    /**
     * @param iterable $data
     * @return array
     */
    protected function _attempt_infer_cell_names(iterable $data): array {
        // We could cast the data to an array,
        // but that would potentially spike memory if it's a large dataset.
        if ($data instanceof Traversable) {
            $iterator = $data instanceof Iterator
                ? clone $data
                : new IteratorIterator($data);

            $iterator->rewind();

            return array_keys($iterator->current());
        }

        return empty($data)
            ? []
            : array_keys($data[0]);
    }

    /**
     * @param iterable $data
     * @param array<string> $cell_names
     * @return Generator<Row>
     */
    protected function _generate_rows(iterable $data, iterable $cell_names): Generator {
        foreach ($data as $item) {
            $cells = [];

            foreach ($cell_names as $cell) {
                $cells[] = Cell::fromValue($item[$cell]);
            }

            yield new Row($cells);
        }
    }

    /**
     * @param string $format
     * @return \OpenSpout\Writer\CSV\Writer|Writer|\OpenSpout\Writer\XLSX\Writer|void
     */
    private function _writer(string $format)
    {
        assert(
            in_array($format, ['xlsx', 'csv', 'ods']),
            'Invalid format'
        );

        return match ($format) {
            'xlsx' => new \OpenSpout\Writer\XLSX\Writer(),
            'csv' => new \OpenSpout\Writer\CSV\Writer(),
            'ods' => new Writer(),
        };
    }

    /**
     * @param array $cells
     * @param iterable $data
     * @param \OpenSpout\Writer\XLSX\Writer|\OpenSpout\Writer\CSV\Writer|Writer $writer
     * @return void
     * @throws IOException
     * @throws WriterNotOpenedException
     */
    public function _add_rows(array $cells, iterable $data, \OpenSpout\Writer\XLSX\Writer|\OpenSpout\Writer\CSV\Writer|Writer $writer): void
    {
        if (empty($cells)) {
            $cells = $this->_attempt_infer_cell_names($data);
        }

        $rows = $this->_generate_rows($data, $cells);

        foreach ($rows as $row) {
            $writer->addRow($row);
        }
    }
}