<?php

namespace Backend\Modules\Analytics\Engine;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

use Backend\Core\Engine\Language as BL;
use Backend\Core\Engine\Model as BackendModel;

/**
 * In this file, the analytics cache is build
 *
 * @author Wouter Sioen <wouter@wijs.be>
 */
class CacheBuilder
{
    /**
     * Write data to cache file
     *
     * @param array $data           The data to write to the cache file.
     * @param int   $startTimestamp The start timestamp for the cache file.
     * @param int   $endTimestamp   The end timestamp for the cache file.
     */
    public function buildCache(array $data, $startTimestamp, $endTimestamp)
    {
        $xml = "<?xml version='1.0' encoding='" . SPOON_CHARSET . "'?>\n";
        $xml .= "<analytics start_timestamp=\"" . $startTimestamp . "\" end_timestamp=\"" . $endTimestamp . "\">\n";

        $xml .= $this->convertDataToXml($data);

        // end xml string
        $xml .= "</analytics>";

        // perform checks for valid xml and throw exception if needed
        $simpleXml = @simplexml_load_string($xml);
        if ($simpleXml === false) {
            throw new BackendException('The xml of the cache file is invalid.');
        }

        // store
        $fs = new Filesystem();
        $fs->dumpFile(
            $this->getCacheFilePath($startTimestamp, $endTimestamp),
            $xml
        );
    }

    /**
     * Fetches the full path for the cache file
     *
     * @param int   $startTimestamp The start timestamp for the cache file.
     * @param int   $endTimestamp   The end timestamp for the cache file.
     */
    protected function getCacheFilePath($startTimestamp, $endTimestamp)
    {
        $siteId = BackendModel::get('current_site')->getId();
        $path = BACKEND_CACHE_PATH . '/Analytics';
        $fileName = $siteId . '_' . $startTimestamp . '_' . $endTimestamp . '.xml';

        return $path . '/' . $fileName;
    }

    protected function convertDataToXml(array $data)
    {
        $xml = '';

        // loop data
        foreach ($data as $type => $records) {
            $attributes = array();

            // there are some attributes
            if (isset($records['attributes']) && !empty($records['attributes'])) {
                // loop em
                foreach ($records['attributes'] as $key => $value) {
                    // add to the attributes string
                    $attributes[] = $key . '="' . $value . '"';
                }
            }

            $xml .= "\t<" . $type . (!empty($attributes) ? ' ' . implode(' ', $attributes) : '') . ">\n";

            // we're not dealing with a page detail
            if (strpos($type, 'page_') === false) {
                // get items
                $items = (isset($records['entries']) ? $records['entries'] : $records);

                // loop data
                foreach ($items as $key => $value) {
                    // skip empty items
                    if ((is_array($value) && empty($value)) || (is_string($value) && trim($value) === '')) {
                        continue;
                    }

                    // value contains an array
                    if (is_array($value)) {
                        // there are values
                        if (!empty($value)) {
                            // build xml
                            $xml .= "\t\t<entry>\n";

                            // loop data
                            foreach ($value as $entryKey => $entryValue) {
                                // build xml
                                $xml .= "\t\t\t<" . $entryKey . "><![CDATA[" . $entryValue . "]]></" . $entryKey . ">\n";
                            }

                            // end xml element
                            $xml .= "\t\t</entry>\n";
                        }
                    } else {
                        // build xml
                        $xml .= "\t\t<" . $key . ">" . $value . "</" . $key . ">\n";
                    }
                }
            } else {
                // we're dealing with a page detail: loop data
                foreach ($records as $subKey => $subItems) {
                    // build xml
                    $xml .= "\t\t<" . $subKey . ">\n";

                    // sub items is an array
                    if (is_array($subItems)) {
                        // loop data
                        foreach ($subItems as $key => $value) {
                            // skip empty items
                            if ((is_array($value) && empty($value)) || trim((string) $value) === '') {
                                continue;
                            }

                            // value contains an array
                            if (is_array($value)) {
                                // there are values
                                if (!empty($value)) {
                                    // build xml
                                    $xml .= "\t\t\t<entry>\n";

                                    // loop data
                                    foreach ($value as $entryKey => $entryValue) {
                                        // build xml
                                        $xml .= "\t\t\t\t<" . $entryKey . "><![CDATA[" . $entryValue . "]]></" . $entryKey . ">\n";
                                    }

                                    // end xml element
                                    $xml .= "\t\t\t</entry>\n";
                                }
                            } else {
                                // build xml
                                $xml .= "\t\t<" . $key . ">" . $value . "</" . $key . ">\n";
                            }
                        }
                    } else {
                        // not an array
                        $xml .= "<![CDATA[" . (string) $subItems . "]]>";
                    }

                    // end xml element
                    $xml .= "\t\t</" . $subKey . ">\n";
                }
            }

            // end xml element
            $xml .= "\t</" . $type . ">\n";
        }

        return $xml;
    }
}