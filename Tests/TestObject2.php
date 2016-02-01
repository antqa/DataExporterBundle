<?php

/*
 * This file is part of the AntQaDataExporterBundle package.
 *
 * (c) ant.qa <https://www.ant.qa/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AntQa\DataExporterBundle\Tests;

class TestObject2
{
    private $col1;

    public function __construct()
    {
        $this->col1 = 'Object two';
    }

    public function setCol1($col1)
    {
        $this->col1 = $col1;
    }

    public function getCol1()
    {
        return $this->col1;
    }
}
