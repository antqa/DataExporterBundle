<?php

/*
 * This file is part of the AntQaDataExporterBundle package.
 *
 * (c) ant.qa <https://www.ant.qa/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AntQa\DataExporterBundle;

use Knp\Bundle\SnappyBundle\Snappy\LoggableGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Templating\EngineInterface;

/**
 * DataExporter
 *
 * @author Piotr Antosik <piotr@ant.qa>
 */
class DataExporter
{
    /**
     * @var array
     */
    protected $columns;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var array
     */
    protected $hooks = [];

    /**
     * @var array
     */
    protected $options;

    /**
     * @var array
     */
    protected $registredBundles;

    /**
     * @var LoggableGenerator|null
     */
    protected $knpSnappyPdf;

    /**
     * @var EngineInterface
     */
    protected $templating;

    /**
     * @var \Symfony\Component\PropertyAccess\PropertyAccessor
     */
    private $propertyAccessor;

    /**
     * @param EngineInterface        $templating
     */
    public function __construct(EngineInterface $templating)
    {
        $this->templating = $templating;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param LoggableGenerator|null $knpSnappyPdf
     */
    public function setSnappy(LoggableGenerator $knpSnappyPdf = null)
    {
        $this->knpSnappyPdf = $knpSnappyPdf;
    }

    /**
     * @param array $options
     *
     * @throws \Exception
     */
    public function setOptions($options = [])
    {
        $resolver = new OptionsResolver();

        if (class_exists('Symfony\Component\OptionsResolver\OptionsResolverInterface')) {
            $this->setDefaultOptions($resolver);
        } else {
            $this->configureOptions($resolver);
        }

        $this->options = $resolver->resolve($options);

        switch ($this->getFormat()) {
            case 'csv':
                $this->data = [];
                break;
            case 'xls':
                $this->openXLS();
                break;
            case 'html':
                $this->openHTML();
                break;
            case 'xml':
                $this->openXML();
                break;
            case 'pdf':
                if (null === $this->knpSnappyPdf) {
                    throw new \Exception('KnpSnappyBundle must be installed');
                }

                break;
        }

        if (true === $this->getSkipHeader() && $this->getFormat() !== 'csv') {
            throw new \Exception('Only CSV support skip_header option!');
        }
    }

    /**
     * BC for SF < 2.7
     *
     * @param OptionsResolverInterface $resolver
     */
    protected function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $this->configureOptions($resolver);
    }

    /**
     * @param OptionsResolver $resolver
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
                'format' => 'csv',
                'charset'=> 'utf-8',
                'fileName' => function (Options $options) {
                        $date = new \DateTime();

                        return sprintf('Data export %s.%s', $date->format('Y-m-d H:i:s'), $options['format']);
                    },
                'memory' => false,
                'skipHeader' => false,
                'allowNull' => false,
                'nullReplace' => false,
                'separator' => function (Options $options) {
                        if ('csv' === $options['format']) {
                            return ',';
                        }

                        return null;
                    },
                'escape' => function (Options $options) {
                        if ('csv' === $options['format']) {
                            return '\\';
                        }

                        return null;
                    },
                'onlyContent' => function(Options $options) {
                        if ('html' === $options['format']) {
                            return false;
                        }

                        return null;
                    },
                'template' => function (Options $options) {
                        if ('pdf' === $options['format'] || 'render' === $options['format']) {
                            return 'AntQaDataExporterBundle::base.pdf.twig';
                        }

                        return null;
                    },
                'template_vars' => [],
                'pdfOptions' => function (Options $options) {
                        if ('pdf' === $options['format']) {
                            return [
                                'orientation' => 'Landscape'
                            ];
                        }

                        return null;
                    }
            ]);
        $resolver->setAllowedValues('format', ['csv', 'xls', 'html', 'xml', 'json', 'pdf', 'listData', 'render']);
        $resolver
            ->addAllowedTypes('charset', 'string')
            ->addAllowedTypes('fileName', 'string')
            ->addAllowedTypes('memory', ['null', 'bool'])
            ->addAllowedTypes('skipHeader', ['null', 'bool'])
            ->addAllowedTypes('separator', ['null', 'string'])
            ->addAllowedTypes('escape', ['null', 'string'])
            ->addAllowedTypes('allowNull', 'bool')
            ->addAllowedTypes('nullReplace', 'bool')
            ->addAllowedTypes('template', ['null', 'string'])
            ->addAllowedTypes('template_vars', 'array')
            ->addAllowedTypes('pdfOptions', ['null', 'array'])
            ->addAllowedTypes('onlyContent', ['null', 'bool']);
    }

    /**
     * @return string
     */
    private function getFormat()
    {
        return $this->options['format'];
    }

    /**
     * @return Boolean|null
     */
    private function getInMemory()
    {
        return $this->options['memory'];
    }

    /**
     * @return string
     */
    private function getFileName()
    {
        return $this->options['fileName'];
    }

    /**
     * @return Boolean|null
     */
    private function getSkipHeader()
    {
        return $this->options['skipHeader'];
    }

    /**
     * @return Boolean|null
     */
    private function getOnlyContent()
    {
        return $this->options['onlyContent'];
    }

    /**
     * @return string|null
     */
    private function getTemplate()
    {
        return $this->options['template'];
    }

    /**
     * @return array
     */
    private function getTemplateVars()
    {
        return $this->options['template_vars'];
    }

    /**
     * @return array|null
     */
    private function getPdfOptions()
    {
        return $this->options['pdfOptions'];
    }

    /**
     * @return string
     */
    private function getCharset()
    {
        return $this->options['charset'];
    }

    /**
     * @return string
     */
    private function getSeparator()
    {
        return $this->options['separator'];
    }

    /**
     * @return string
     */
    private function getEscape()
    {
        return $this->options['escape'];
    }

    private function getAllowNull()
    {
        return $this->options['allowNull'];
    }

    private function getNullReplace()
    {
        return $this->options['nullReplace'];
    }

    /**
     * @return $this
     */
    private function openXML()
    {
        $this->data = '<?xml version="1.0" encoding="' . $this->getCharset() . '"?><table>';

        return $this;
    }

    /**
     * @return $this
     */
    private function closeXML()
    {
        $this->data .= "</table>";

        return $this;
    }

    /**
     * @return $this
     */
    private function openXLS()
    {
        $this->data = sprintf("<!DOCTYPE ><html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=%s\" /><meta name=\"ProgId\" content=\"Excel.Sheet\"><meta name=\"Generator\" content=\"https://github.com/piotrantosik/DataExporter\"></head><body><table>", $this->getCharset());

        return $this;
    }

    /**
     * @return $this
     */
    private function closeXLS()
    {
        $this->data .= "</table></body></html>";

        return $this;
    }

    /**
     * @return $this
     */
    private function openHTML()
    {
        if (!$this->getOnlyContent()) {
            $this->data = "<!DOCTYPE ><html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=" . $this->getCharset() . "\" /><meta name=\"Generator\" content=\"https://github.com/piotrantosik/DataExporter\"></head><body><table>";
        } else {
            $this->data = '<table>';
        }

        return $this;
    }

    /**
     * @return $this
     */
    private function closeHTML()
    {
        if (!$this->getOnlyContent()) {
            $this->data .= "</table></body></html>";
        } else {
            $this->data .= "</table>";
        }

        return $this;
    }

    /**
     * @param string $data
     * @param string $column
     *
     * @return string
     */
    private function escape(&$data, &$column)
    {
        $hooks = &$this->hooks;
        //check for hook
        if (count($hooks) > 0 && isset($hooks[$column])) {
            //check for closure
            if (false === is_array($hooks[$column])) {
                $data = $hooks[$column]($data);
            } else {
                $refl = new \ReflectionMethod($hooks[$column][0], $hooks[$column][1]);
                if (is_object($hooks[$column][0])) {
                    $obj = $hooks[$column][0];
                    $method = $hooks[$column][1];
                    $data = $obj->$method($data);
                } elseif ($refl->isStatic()) {
                    $data = forward_static_call([$hooks[$column][0], $hooks[$column][1]], $data);
                } else {
                    $object = $hooks[$column][0];
                    $obj = new $object;

                    $method = $hooks[$column][1];
                    $data = $obj->$method($data);
                }
            }
        }

        //replace new line character
        $data = preg_replace("/\r\n|\r|\n/", ' ', $data);

        $data = mb_ereg_replace(
            sprintf('%s', $this->getSeparator()),
            sprintf('%s', $this->getEscape()),
            $data
        );

        if ('xml' === $this->getFormat()) {
            if (version_compare(phpversion(), '5.4.0', '>=')) {
                $data = htmlspecialchars($data, ENT_XML1);
            } else {
                $data = htmlspecialchars($data);
            }
        }
        //strip html tags
        if (in_array($this->getFormat(), ['csv', 'xls'])) {
            $data = strip_tags($data);
        }

        return $data;
    }

    /**
     * @param array|\Closure  $function
     * @param string          $column
     *
     * @return $this|bool
     * @throws \BadFunctionCallException
     * @throws \InvalidArgumentException
     * @throws \LengthException
     */
    public function addHook($function, $column)
    {
        //check for closure
        if (false === is_array($function)) {
            $functionReflected = new \ReflectionFunction($function);
            if ($functionReflected->isClosure()) {
                $this->hooks[$column] = $function;

                return true;
            }
        } else {
            if (2 !== count($function)) {
                throw new \LengthException('Exactly two parameters required!');
            }
            if (false === is_callable($function)) {
                throw new \BadFunctionCallException(sprintf(
                    'Function %s in class %s is non callable!',
                    $function[1],
                    $function[0]
                ));
            }

            $this->hooks[$column] = [$function[0], $function[1]];
        }

        return $this;
    }

    /**
     * @param mix $row
     *
     * @return bool
     *
     * @throws \Exception
     * @throws \Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException
     */
    private function addRow(&$row)
    {
        $countColumns = count($this->columns);
        $tempRow = new \SplFixedArray($countColumns);

        for ($i = 0; $i < $countColumns; $i++) {
            try {
                $value = $this->propertyAccessor->getValue($row, $this->columns[$i]);
            } catch (UnexpectedTypeException $exception) {
                if (true === $this->getAllowNull()) {
                    $value = $this->getNullReplace();
                } else {
                    throw $exception;
                }
            }

            $tempRow[$i] = $this->escape($value, $this->columns[$i]);
        }

        switch ($this->getFormat()) {
            case 'csv':
                $this->data[] = implode($this->getSeparator(), $tempRow->toArray());
                break;
            case 'json':
                $this->data[] = array_combine($this->data[0], $tempRow->toArray());
                break;
            case 'pdf':
            case 'listData':
            case 'render':
                $this->data[] = $tempRow->toArray();
                break;
            case 'xls':
            case 'html':
                $this->data .= '<tr>';
                foreach ($tempRow as $val) {
                    $this->data .= '<td>' . $val . '</td>';
                }
                $this->data .= '</tr>';
                break;
            case 'xml':
                $this->data .= '<row>';
                $index = 0;
                foreach ($tempRow as $val) {
                    $this->data .= '<column name="' . $this->columns[$index] . '">' . $val . '</column>';
                    $index++;
                }
                $this->data .= '</row>';
                break;
        }

        return true;
    }

    /**
     * @param array $rows
     *
     * @return $this
     * @throws \RuntimeException
     */
    public function setData(&$rows)
    {
        if (empty($this->columns)) {
            throw new \RuntimeException('First use setColumns to set columns to export!');
        }

        foreach ($rows as $row) {
            $this->addRow($row);
            unset($row);
        }

        //close tags
        $this->closeData();

        return $this;
    }

    /**
     * @return $this
     */
    private function closeData()
    {
        switch ($this->getFormat()) {
            case 'json':
                //remove first row from data
                unset($this->data[0]);
                break;
            case 'xls':
                $this->closeXLS();
                break;
            case 'html':
                $this->closeHTML();
                break;
            case 'xml':
                $this->closeXML();
                break;
        }

        return $this;
    }

    /**
     * @param array $haystack
     *
     * @return mixed
     */
    private function getLastKeyFromArray(array $haystack)
    {
        end($haystack);

        return key($haystack);
    }

    /**
     * @param array $haystack
     *
     * @return mixed
     */
    private function getFirstKeyFromArray(array $haystack)
    {
        reset($haystack);

        return key($haystack);
    }

    /**
     * @param string  $column
     * @param integer $key
     * @param array   $columns
     *
     * @return $this
     */
    private function setColumn($column, $key, $columns)
    {
        if (true === is_int($key)) {
            $this->columns[] = $column;
        } else {
            $this->columns[] = $key;
        }

        if (in_array($this->getFormat(), ['csv', 'json', 'xls'], true)) {
            $column = strip_tags($column);
        }

        if ('csv' === $this->getFormat() && false === $this->getSkipHeader()) {
            //last item
            if (isset($this->data[0])) {
                //last item
                if ($key !== $this->getLastKeyFromArray($columns)) {
                    $this->data[0] = $this->data[0] . $column . $this->getSeparator();
                } else {
                    $this->data[0] = $this->data[0] . $column;
                }
            } else {
                $this->data[] = $column . $this->getSeparator();
            }
        } elseif (true === in_array($this->getFormat(), ['xls', 'html'], true)) {
            //first item
            if ($key === $this->getFirstKeyFromArray($columns)) {
                $this->data .= '<tr>';
            }

            $this->data .= sprintf('<td>%s</td>', $column);
            //last item
            if ($key === $this->getLastKeyFromArray($columns)) {
                $this->data .= '</tr>';
            }
        } elseif ('json' === $this->getFormat()) {
            $this->data[0] = array_values($columns);
        } elseif ('pdf' === $this->getFormat() || 'render' === $this->getFormat()) {
            $this->data[0] = array_values($columns);
        } elseif ('listData' === $this->getFormat()) {
            $this->data[0] = array_values($columns);
        }

        return $this;
    }

    /**
     * @param array $columns
     *
     * @return $this
     * @throws \RuntimeException
     */
    public function setColumns(array $columns)
    {
        $format = $this->getFormat();
        if (empty($format)) {
            throw new \RuntimeException(sprintf('First use setOptions!'));
        }

        foreach ($columns as $key => $column) {
            $this->setColumn($column, $key, $columns);
        }

        return $this;
    }


    /**
     * @return string
     */
    private function prepareCSV()
    {
        return implode("\n", $this->data);
    }

    /**
     * @return string|Response
     */
    public function render()
    {
        $response = new Response;

        switch ($this->getFormat()) {
            case 'csv':
                $response->headers->set('Content-Type', 'text/csv');
                $response->setContent($this->prepareCSV());
                break;
            case 'json':
                $response->headers->set('Content-Type', 'application/json');
                //remove first row from data
                unset($this->data[0]);
                $response->setContent(json_encode($this->data));
                break;
            case 'xls':
                $response->headers->set('Content-Type', 'application/vnd.ms-excel');
                $response->setContent($this->data);
                break;
            case 'html':
                $response->headers->set('Content-Type', 'text/html');
                $response->setContent($this->data);
                break;
            case 'xml':
                $response->headers->set('Content-Type', 'application/xml');
                $response->setContent($this->data);
                break;
            case 'pdf':
                $columns = $this->data[0];
                unset($this->data[0]);
                $response->headers->set('Content-Type', 'application/pdf');
                $response->setContent(
                    $this->knpSnappyPdf->getOutputFromHtml(
                        $this->templating->render($this->getTemplate(), [
                                'columns'  => $columns,
                                'data' => $this->data,
                                'template_vars' => $this->getTemplateVars(),
                            ]),
                        $this->getPdfOptions()
                    )
                );
                break;
            case 'render':
                $columns = $this->data[0];
                unset($this->data[0]);
                $response->headers->set('Content-Type', 'text/plain');
                $response->setContent(
                    $this->templating->render($this->getTemplate(), [
                            'columns'  => $columns,
                            'data' => $this->data,
                            'template_vars' => $this->getTemplateVars(),
                        ])
                );
                break;
            case 'listData':
                $columns = $this->data[0];
                unset($this->data[0]);

                return ['columns' => $columns, 'rows' => $this->data];
        }

        if ($this->getInMemory()) {
            return $response->getContent();
        }

        $response->headers->set('Cache-Control', 'public');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->getFileName() . '"');

        return $response;
    }
}
