<?php

// '/../vendor/autoload.php'
use OpenSpout\Common\Entity\Cell;

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
     * @throws \OpenSpout\Writer\Exception\WriterException @see $writer->addRow(...)
     * @throws \OpenSpout\Common\Exception\IOException @see $writer->addRow(...)
     */
    public function _export_data_to_browser(iterable $data, string $output, array $cells = [], string $format = 'xlsx'): void {
        $writer = $this->_writer($format, $cells, $data);

        $writer->openToBrowser($output);
    }

    /**
     * Export data to Excel to a local file.
     *
     * @param iterable $data
     * @param string $output
     * @param array<string> $cells An array of cell names
     * @param string $format
     * @return void
     * @throws \OpenSpout\Writer\Exception\WriterException @see $writer->addRow(...)
     * @throws \OpenSpout\Common\Exception\IOException @see $writer->addRow(...)
     */
    public function _export_data_to_file(iterable $data, string $output, array $cells = [], string $format = 'xlsx'): void {
        $writer = $this->_writer($format, $cells, $data);

        $writer->openToFile($output);
    }

    /**
     * @param iterable $data
     * @return array
     */
    protected function _attempt_infer_cell_names(iterable $data): array {
        // We could cast the data to an array,
        // but that would potentially spike memory if it's a large dataset.
        if ($data instanceof \Traversable) {
            $iterator = $data instanceof \Iterator
                ? clone $data
                : new \IteratorIterator($data);

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
     * @return Generator<\OpenSpout\Common\Entity\Row>
     */
    protected function _generate_rows(iterable $data, iterable $cell_names): Generator {
        foreach ($data as $item) {
            $cells = [];

            foreach ($cell_names as $cell) {
                $cells[] = Cell::fromValue($item[$cell]);
            }

            yield new \OpenSpout\Common\Entity\Row($cells);
        }
    }

    /**
     * @param string $format
     * @param array $cells
     * @param iterable $data
     * @return \OpenSpout\Writer\CSV\Writer|\OpenSpout\Writer\ODS\Writer|\OpenSpout\Writer\XLSX\Writer|void
     * @throws \OpenSpout\Common\Exception\IOException
     * @throws \OpenSpout\Writer\Exception\WriterNotOpenedException
     */
    private function _writer(string $format, array $cells, iterable $data)
    {
        assert(
            in_array($format, ['xlsx', 'csv', 'ods']),
            'Invalid format'
        );

        $writer = match ($format) {
            'xlsx' => new \OpenSpout\Writer\XLSX\Writer(),
            'csv' => new \OpenSpout\Writer\CSV\Writer(),
            'ods' => new \OpenSpout\Writer\ODS\Writer(),
        };

        if (empty($cells)) {
            $cells = $this->_attempt_infer_cell_names($data);
        }

        $rows = $this->_generate_rows($data, $cells);

        foreach ($rows as $row) {
            $writer->addRow($row);
        }
        return $writer;
    }
}