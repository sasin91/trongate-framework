# Excel export support for Trongate

[!NOTE]
This module uses OpenSpout through Composer.

### Test cases
```php
    public function excel_assoc_data(): void {
        $this->module('excel');
        $this->excel->_export_data_to_browser([
            ['name' => 'John Doe', 'email' => 'john@example.com'],
            ['name' => 'Jane Doe', 'email' => 'jane@example.com']
        ], 'assoc_data.xlsx');
    }

    public function excel_indexed_data(): void {
        $this->module('excel');
        $this->excel->_export_data_to_browser([
            ['Johny Indexed', 'johny@indexed.test'],
            ['Jane Indexed', 'jane@indexed.test']
        ], 'indexed_data.xlsx');
    }

    public function excel_styled(): void
    {
        $this->module('excel');
        $this->excel->_export_data_to_browser(
            [
                ['Plain jane', 'plain@jane.test', '1234567890'],
                function ($style) {
                    $style->setFontBold(true);

                    return ['Bold john', 'bold@john.test', '0987654321'];
                },
                [
                    function ($style) {
                        $style->setBackgroundColor('000000');
                        $style->setFontColor('FFFFFF');
                        $style->setFontSize(10);

                        return 'Ze';
                    },
                    function ($style) {
                        $style->setBackgroundColor('FFFFFF');
                        $style->setFontColor('000000');
                        $style->setFontSize(8);
                        $style->setFontItalic(true);

                        return 'Bra';
                    },
                    '029382910'
                ],
            ],
            'styled.xlsx'
        );
    }

    public function excel_custom_header(): void
    {
        $this->module('excel');
        $this->excel->_export_data_to_browser(
            data: [
                ['John Doe', 'abcdef'],
                ['Jane Doe', 'ghijkl'],
                ['John Smith', 'mnopqr'],
                ['Jane Smith', 'stuvwx'],
                ['John Johnson', 'yz1234'],
                ['Jane Johnson', '567890'],
                ['John Brown', 'abc123'],
                ['Jane Brown', 'def456'],
                ['John White', 'ghi789'],
                ['Jane White', 'jkl012'],
                ['Jessie Jane', 'mno345'],
                ['Jessie John', 'pqraad']
            ],
            output: 'custom_header.xlsx',
            writerSetup: function ($options) {
                $options->setHeaderFooter(new HeaderFooter(
                    'oddHeader',
                    'oddFooter',
                    'evenHeader',
                    'evenFooter',
                    true  // differentOddEven, default value is false
                ));

            }
        );
    }
```