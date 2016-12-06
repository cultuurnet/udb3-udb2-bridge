<?php

namespace CultuurNet\UDB3\UDB2\XSD;

use CultuurNet\UDB3\UDB2\XML\XMLValidationError;
use CultuurNet\UDB3\UDB2\XML\XMLValidationServiceInterface;

class XSDAwareXMLValidationService implements XMLValidationServiceInterface
{
    /**
     * @var XSDReaderInterface
     */
    private $xsdReader;

    /**
     * @var int
     */
    private $minimumReportLevel;

    /**
     * @var bool
     */
    private $includeErrorsWithoutLineNumber;

    /**
     * @param XSDReaderInterface $xsdReader
     *
     * @param int $minimumReportLevel
     *   One of LIBXML_ERR_WARNING, LIBXML_ERR_ERROR, LIBXML_ERR_FATAL
     *
     * @param bool $includeErrorsWithoutLineNumber
     *   Most (if not all) errors without line number are XSD validation
     *   errors that are caused by an earlier XML validation error.
     */
    public function __construct(
        XSDReaderInterface $xsdReader,
        $minimumReportLevel = LIBXML_ERR_ERROR,
        $includeErrorsWithoutLineNumber = false
    ) {
        $this->xsdReader = $xsdReader;
        $this->minimumReportLevel = (int) $minimumReportLevel;
        $this->includeErrorsWithoutLineNumber = $includeErrorsWithoutLineNumber;
    }

    /**
     * @param string $xml
     *   XML source code.
     *
     * @return XMLValidationError[]
     *   All validation errors, if any.
     */
    public function validate($xml)
    {
        $errors = [];

        // Enable custom error handling.
        $previousInternalErrorsFlag = libxml_use_internal_errors(true);

        // Clear errors from previous classes, if any.
        libxml_clear_errors();

        $xmlDocument = new \DOMDocument();
        $xmlDocument->loadXML($xml);

        $xsd = $this->xsdReader->read();

        if (!$xmlDocument->schemaValidateSource($xsd->getContent())) {
            $libXMLErrors = array_filter(
                libxml_get_errors(),
                function (\LibXMLError $libXMLError) {
                    return $libXMLError->level >= $this->minimumReportLevel &&
                        ($libXMLError->line >= 0 || $this->includeErrorsWithoutLineNumber);
                }
            );

            $libXMLErrors = array_values($libXMLErrors);

            $errors = array_map(
                function (\LibXMLError $libXMLError) {
                    return new XMLValidationError(
                        rtrim($libXMLError->message),
                        $libXMLError->line,
                        $libXMLError->column
                    );
                },
                $libXMLErrors
            );

            // Clear errors for any future validations or other classes.
            libxml_clear_errors();
        }

        libxml_use_internal_errors($previousInternalErrorsFlag);

        return $errors;
    }
}
