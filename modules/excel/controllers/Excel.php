<?php

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Writer\Exception\WriterException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;

// '/../vendor/autoload.php'
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' .  DIRECTORY_SEPARATOR . 'autoload.php';

class Excel extends Trongate
{
    /**
     * Export data to Excel and send it to the browser for download or preview.
     *
     * @param iterable $data
     * @param string $output
     * @param string $format
     * @param callable|null $writerSetup
     * @throws IOException @see $writer->addRow(...)
     * @throws WriterNotOpenedException
     * @return void
     */
    public function _export_data_to_browser(iterable $data, string $output, string $format = 'xlsx', callable $writerSetup = null): void {
        $writer = $this->_writer($format, $writerSetup);

        $writer->openToBrowser($output);

        $this->_add_rows($data, $writer);

        $writer->close();

        header('Content-Type', 'application/vnd.ms-excel');
    }

    /**
     * Export data to Excel to a local file.
     *
     * @param iterable $data
     * @param string $output
     * @param string $format
     * @return void
     * @throws WriterException @see $writer->addRow(...)
     * @throws IOException @see $writer->addRow(...)
     */
    public function _export_data_to_file(iterable $data, string $output, string $format = 'xlsx'): void {
        $writer = $this->_writer($format);

        $writer->openToFile($output);

        $this->_add_rows($data, $writer);

        $writer->close();
    }

    /**
     * @param iterable $data
     * @return Generator<Row>
     */
    protected function _generate_rows(iterable $data): Generator {
        foreach ($data as $item) {
            $style = null;

            if (is_callable($item)) {
                $style = new Style();

                // nb: reassign $item to the result of the callable
                $item = $item($style);
            }

            $cells = [];

            foreach ($item as $value) {
                $style = null;

                if (is_callable($value)) {
                    $style = new Style();

                    // nb: reassign $value to the result of the callable
                    $value = $value($style);
                }

                $cells[] = Cell::fromValue($value, $style);
            }

            yield new Row($cells, $style);
        }
    }

    /**
     * @param string $format
     * @param callable|null $setup
     * @return \OpenSpout\Writer\CSV\Writer|Writer|\OpenSpout\Writer\XLSX\Writer
     */
    private function _writer(string $format, callable $setup = null): \OpenSpout\Writer\CSV\Writer|\OpenSpout\Writer\ODS\Writer|\OpenSpout\Writer\XLSX\Writer
    {
        assert(
            in_array($format, ['xlsx', 'csv', 'ods']),
            'Invalid format'
        );

        $setup = $setup ?? fn () => null;
        
        switch ($format) {
            default:
            case 'xlsx':
                $options = new \OpenSpout\Writer\XLSX\Options();
                $setup($options);
                return new \OpenSpout\Writer\XLSX\Writer($options);
                
            case 'csv':
                $options = new \OpenSpout\Writer\CSV\Options();
                $setup($options);
                return new \OpenSpout\Writer\CSV\Writer($options);
            case 'ods':
                $options = new \OpenSpout\Writer\ODS\Options();
                $setup($options);
                return new \OpenSpout\Writer\ODS\Writer($options);
        }
    }

    /**
     * @param iterable $data
     * @param \OpenSpout\Writer\XLSX\Writer|\OpenSpout\Writer\CSV\Writer|\OpenSpout\Writer\ODS\Writer $writer
     * @return void
     * @throws IOException
     * @throws WriterNotOpenedException
     */
    public function _add_rows(iterable $data, \OpenSpout\Writer\XLSX\Writer|\OpenSpout\Writer\CSV\Writer|\OpenSpout\Writer\ODS\Writer $writer): void
    {
        $rows = $this->_generate_rows($data);

        foreach ($rows as $row) {
            $writer->addRow($row);
        }
    }
}