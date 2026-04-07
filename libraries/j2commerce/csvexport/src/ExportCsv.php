<?php

namespace J2Commerce\Library\ExportCsv;

class ExportCsv
{
    protected $export_path          = '';
    protected $headers              = [];
    protected $resulting_csv_values = [];
    protected $delimiter            = ",";
    protected $enclosure            = '"';
    protected $escape_char          = "\\";

    public function __construct($export_file_path = '', $header = [])
    {
        $this->setCsvPath($export_file_path);
        $this->setHeader($header);
    }

    /**
     * @param $path - export csv path
     */
    public function setCsvPath($path)
    {
        $this->export_path = $path;
    }

    /**
     * @param array $headers - csv header fields
     */
    public function setHeader($headers = [])
    {
        $this->headers = $headers;
    }

    /**
     * @param array $csv_values - csv values
     * @throws \Exception
     */
    public function setCsvValues($csv_values = [])
    {
        //validate csv value array
        if (!\is_array($csv_values)) {
            throw new \Exception('Array value only accepted');
        }
        if (empty($this->headers)) {
            throw new \Exception('Please set header first');
        }
        if (empty($csv_values)) {
            throw new \Exception('Empty value not allowed');
        }
        foreach ($csv_values as $csv_value) {
            $single_csv_record = [];
            foreach ($this->headers as $header_key) {
                if (isset($csv_value[$header_key])) {
                    if (\is_array($csv_value[$header_key])) {
                        $csv_value[$header_key] = implode(',', $csv_value[$header_key]);
                    }
                    array_push($single_csv_record, $csv_value[$header_key]);
                }
            }
            $this->resulting_csv_values[] = $single_csv_record;
        }
    }

    /**
     * Export Csv file
     * @throws \Exception
     */
    public function writeCsv()
    {
        if (empty($this->headers)) {
            throw new \Exception('Please set header first');
        }
        if (empty($this->resulting_csv_values)) {
            throw new \Exception('Please set at least one csv record');
        }
        if (empty($this->export_path)) {
            throw new \Exception('Export csv path invalid');
        }
        if (!file_exists($this->export_path)) {
            $file = fopen($this->export_path, "a");
            fputcsv($file, $this->headers, $this->delimiter, $this->enclosure, $this->escape_char);
        } else {
            $file = fopen($this->export_path, "a");
        }
        foreach ($this->resulting_csv_values as $single_csv_record) {
            fputcsv($file, $single_csv_record, $this->delimiter, $this->enclosure, $this->escape_char);
        }
        fclose($file);
    }

    public function setDelimiter($delimiter = ",")
    {
        $this->delimiter = $delimiter;
    }

    public function setEnclosure($enclosure = '"')
    {
        $this->enclosure = $enclosure;
    }

    public function setEscapeChar($escape_char = '"')
    {
        $this->escape_char = $escape_char;
    }
}
