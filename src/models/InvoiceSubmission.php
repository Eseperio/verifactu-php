<?php
namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\RectificationType;
use eseperio\verifactu\models\enums\YesNoType;
use eseperio\verifactu\models\enums\ThirdPartyOrRecipientType;

/**
 * Model representing an invoice submission ("Alta").
 * Based on: RegistroAlta (SuministroInformacion.xsd.xml)
 * Original schema: RegistroAltaType
 * @see docs/aeat/esquemas/SuministroInformacion.xsd.xml
 */
class InvoiceSubmission extends InvoiceRecord
{
    /**
     * Issuer company or person name (NombreRazonEmisor)
     * Example: 'Acme Corp S.L.'
     * @var string
     */
    public $issuerName;

    /**
     * Rectification data for invoices (FacturasRectificadas, FacturasSustituidas, optional).
     * Structure:
     *   [
     *      'rectified' => [ // FacturasRectificadas
     *          [
     *              'issuerNif' => 'B12345678', // NIF of the original invoice issuer
     *              'seriesNumber' => 'A-2023/007', // Series and number of rectified invoice
     *              'issueDate' => '2023-09-12' // Date of rectified invoice (YYYY-MM-DD)
     *          ],
     *          ...
     *      ],
     *      'substituted' => [ // FacturasSustituidas
     *          [
     *              'issuerNif' => 'B12345678', // NIF of the original invoice issuer
     *              'seriesNumber' => 'B-2023/010', // Series and number of substituted invoice
     *              'issueDate' => '2023-08-15' // Date of substituted invoice (YYYY-MM-DD)
     *          ],
     *          ...
     *      ]
     *   ]
     * Each list can be empty or omitted if not applicable.
     * @var array
     */
    private $rectificationData = [];

    /**
     * Invoice type (TipoFactura)
     * @var \eseperio\verifactu\models\enums\InvoiceType
     */
    public $invoiceType;

    /**
     * Rectification type (TipoRectificativa, optional)
     * Only for rectifying invoices.
     * @var \eseperio\verifactu\models\enums\RectificationType|null
     */
    public $rectificationType;

    /**
     * Operation date (FechaOperacion, optional)
     * @var string|null
     */
    public $operationDate;

    /**
     * Operation description (DescripcionOperacion)
     * @var string
     */
    public $operationDescription;

    /**
     * Simplified invoice indicator (FacturaSimplificadaArt7273, optional)
     * @var \eseperio\verifactu\models\enums\YesNoType|null
     */
    public $simplifiedInvoice;

    /**
     * Invoice without recipient identification (FacturaSinIdentifDestinatarioArt61d, optional)
     * @var \eseperio\verifactu\models\enums\YesNoType|null
     */
    public $invoiceWithoutRecipient;

    /**
     * Macrodata indicator (Macrodato, optional)
     * @var \eseperio\verifactu\models\enums\YesNoType|null
     */
    public $macrodata;

    /**
     * Issued by third party or recipient (EmitidaPorTerceroODestinatario, optional)
     * @var \eseperio\verifactu\models\enums\ThirdPartyOrRecipientType|null
     */
    public $issuedBy;

    /**
     * Third party (Tercero, optional)
     * @var \eseperio\verifactu\models\LegalPerson|null
     */
    private $thirdParty;

    /**
     * Recipients list (Destinatarios, optional).
     * @var \eseperio\verifactu\models\LegalPerson[]
     */
    private $recipients = [];

    /**
     * Coupon indicator (Cupon, optional)
     * @var \eseperio\verifactu\models\enums\YesNoType|null
     */
    public $coupon;

    /**
     * Tax breakdown (Desglose)
     * @var \eseperio\verifactu\models\Breakdown
     */
    private $breakdown;

    /**
     * Rectification breakdown (ImporteRectificacion, optional)
     * @var \eseperio\verifactu\models\RectificationBreakdown|null
     */
    private $rectificationBreakdown;

    /**
     * Tax amount (CuotaTotal). Total amount of taxes applied to the invoice.
     * @var float
     */
    public $taxAmount;

    /**
     * Total invoice amount (ImporteTotal). Final total to be paid for the invoice.
     * @var float
     */
    public $totalAmount;

    /**
     * Invoice agreement registration number (NumRegistroAcuerdoFacturacion, optional)
     * @var string|null
     */
    public $invoiceAgreementNumber;

    /**
     * System agreement ID (IdAcuerdoSistemaInformatico, optional)
     * @var string|null
     */
    public $systemAgreementId;

    /**
     * Get the rectification data
     * @return array
     */
    public function getRectificationData()
    {
        return $this->rectificationData;
    }

    /**
     * Set the rectification data
     * @param array $rectificationData Array of rectification data
     * @return $this
     */
    public function setRectificationData($rectificationData)
    {
        $this->rectificationData = $rectificationData;
        return $this;
    }

    /**
     * Add a rectified invoice
     * @param string $issuerNif NIF of the original invoice issuer
     * @param string $seriesNumber Series and number of rectified invoice
     * @param string $issueDate Date of rectified invoice (YYYY-MM-DD)
     * @return $this
     */
    public function addRectifiedInvoice($issuerNif, $seriesNumber, $issueDate)
    {
        if (!isset($this->rectificationData['rectified'])) {
            $this->rectificationData['rectified'] = [];
        }

        $this->rectificationData['rectified'][] = [
            'issuerNif' => $issuerNif,
            'seriesNumber' => $seriesNumber,
            'issueDate' => $issueDate
        ];

        return $this;
    }

    /**
     * Add a substituted invoice
     * @param string $issuerNif NIF of the original invoice issuer
     * @param string $seriesNumber Series and number of substituted invoice
     * @param string $issueDate Date of substituted invoice (YYYY-MM-DD)
     * @return $this
     */
    public function addSubstitutedInvoice($issuerNif, $seriesNumber, $issueDate)
    {
        if (!isset($this->rectificationData['substituted'])) {
            $this->rectificationData['substituted'] = [];
        }

        $this->rectificationData['substituted'][] = [
            'issuerNif' => $issuerNif,
            'seriesNumber' => $seriesNumber,
            'issueDate' => $issueDate
        ];

        return $this;
    }

    /**
     * Get the recipients list
     * @return \eseperio\verifactu\models\LegalPerson[]
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * Add a recipient to the invoice
     * @param \eseperio\verifactu\models\LegalPerson|array $recipient Recipient data
     * @return $this
     */
    public function addRecipient($recipient)
    {
        if (is_array($recipient)) {
            $legalPerson = new LegalPerson();
            $legalPerson->name = $recipient['name'] ?? null;

            if (isset($recipient['nif'])) {
                $legalPerson->nif = $recipient['nif'];
            } elseif (isset($recipient['otherId'])) {
                $legalPerson->setOtherId($recipient['otherId']);
            }

            $this->recipients[] = $legalPerson;
        } else {
            $this->recipients[] = $recipient;
        }

        return $this;
    }

    /**
     * Legacy method for backward compatibility
     * @param string $nif Recipient NIF
     * @param string|null $name Recipient name (optional)
     * @return $this
     * @deprecated Use addRecipient with a LegalPerson object instead
     */
    public function addRecipientLegacy($nif, $name = null)
    {
        $recipient = new LegalPerson();
        $recipient->nif = $nif;
        $recipient->name = $name ?? 'Unknown';

        return $this->addRecipient($recipient);
    }

    /**
     * Get the third party
     * @return \eseperio\verifactu\models\LegalPerson|null
     */
    public function getThirdParty()
    {
        return $this->thirdParty;
    }

    /**
     * Set the third party
     * @param \eseperio\verifactu\models\LegalPerson|array $thirdParty Third party data
     * @return $this
     */
    public function setThirdParty($thirdParty)
    {
        if (is_array($thirdParty)) {
            $legalPerson = new LegalPerson();
            $legalPerson->name = $thirdParty['name'] ?? null;

            if (isset($thirdParty['nif'])) {
                $legalPerson->nif = $thirdParty['nif'];
            } elseif (isset($thirdParty['otherId'])) {
                $legalPerson->setOtherId($thirdParty['otherId']);
            }

            $this->thirdParty = $legalPerson;
        } else {
            $this->thirdParty = $thirdParty;
        }

        return $this;
    }

    /**
     * Get the rectification breakdown
     * @return \eseperio\verifactu\models\RectificationBreakdown|null
     */
    public function getRectificationBreakdown()
    {
        return $this->rectificationBreakdown;
    }

    /**
     * Set the rectification breakdown
     * @param \eseperio\verifactu\models\RectificationBreakdown|array $rectificationBreakdown Rectification breakdown data
     * @return $this
     */
    public function setRectificationBreakdown($rectificationBreakdown)
    {
        if (is_array($rectificationBreakdown)) {
            $rectificationBreakdownObj = new RectificationBreakdown();
            $rectificationBreakdownObj->rectifiedBase = $rectificationBreakdown['rectifiedBase'] ?? null;
            $rectificationBreakdownObj->rectifiedTax = $rectificationBreakdown['rectifiedTax'] ?? null;

            if (isset($rectificationBreakdown['rectifiedEquivalenceSurcharge'])) {
                $rectificationBreakdownObj->rectifiedEquivalenceSurcharge = $rectificationBreakdown['rectifiedEquivalenceSurcharge'];
            }

            $this->rectificationBreakdown = $rectificationBreakdownObj;
        } else {
            $this->rectificationBreakdown = $rectificationBreakdown;
        }

        return $this;
    }

    /**
     * Get the tax breakdown
     * @return \eseperio\verifactu\models\Breakdown
     */
    public function getBreakdown()
    {
        return $this->breakdown;
    }

    /**
     * Add a tax breakdown detail
     * @param \eseperio\verifactu\models\BreakdownDetail|array $detail Breakdown detail
     * @return $this
     */
    public function addBreakdownDetail($detail)
    {
        if ($this->breakdown === null) {
            $this->breakdown = new Breakdown();
        }

        $this->breakdown->addDetail($detail);
        return $this;
    }

    /**
     * Set the tax breakdown
     * @param \eseperio\verifactu\models\Breakdown|array $breakdown Tax breakdown
     * @return $this
     */
    public function setBreakdown($breakdown)
    {
        if (is_array($breakdown)) {
            $breakdownObj = new Breakdown();

            if (isset($breakdown[0]) && is_array($breakdown[0])) {
                // Legacy format support - array of breakdown items
                foreach ($breakdown as $item) {
                    $detail = new BreakdownDetail();

                    if (isset($item['rate'])) $detail->taxRate = $item['rate'];
                    if (isset($item['base'])) $detail->taxableBase = $item['base'];
                    if (isset($item['amount'])) $detail->taxAmount = $item['amount'];

                    // Set default values for required fields
                    if (!isset($detail->operationQualification) && !isset($detail->exemptOperation)) {
                        $detail->operationQualification = \eseperio\verifactu\models\enums\OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
                    }

                    $breakdownObj->addDetail($detail);
                }
            } else {
                // Array with Breakdown properties
                if (isset($breakdown['details'])) {
                    $breakdownObj->setDetails($breakdown['details']);
                }
            }

            $this->breakdown = $breakdownObj;
        } else {
            $this->breakdown = $breakdown;
        }

        return $this;
    }

    /**
     * Legacy method for backward compatibility
     * @param float $rate Tax rate
     * @param float $base Taxable base
     * @param float $amount Tax amount
     * @return $this
     * @deprecated Use addBreakdownDetail instead
     */
    public function addBreakdownItem($rate, $base, $amount)
    {
        $detail = new BreakdownDetail();
        $detail->taxRate = $rate;
        $detail->taxableBase = $base;
        $detail->taxAmount = $amount;
        $detail->operationQualification = \eseperio\verifactu\models\enums\OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;

        return $this->addBreakdownDetail($detail);
    }

    /**
     * Returns validation rules for invoice submission.
     * Merges parent rules with specific rules for invoice submission.
     * @return array
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            [['issuerName', 'invoiceType', 'operationDescription', 'breakdown', 'taxAmount', 'totalAmount'], 'required'],
            [['issuerName', 'operationDescription', 'operationDate', 'invoiceAgreementNumber', 'systemAgreementId'], 'string'],
            ['invoiceType', function($value) {
                return ($value instanceof InvoiceType) ? true : 'Must be an instance of InvoiceType.';
            }],
            ['rectificationType', function($value) {
                if ($value === null) return true;
                return ($value instanceof RectificationType) ? true : 'Must be an instance of RectificationType.';
            }],
            ['simplifiedInvoice', function($value) {
                if ($value === null) return true;
                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['invoiceWithoutRecipient', function($value) {
                if ($value === null) return true;
                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['macrodata', function($value) {
                if ($value === null) return true;
                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['coupon', function($value) {
                if ($value === null) return true;
                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['issuedBy', function($value) {
                if ($value === null) return true;
                return ($value instanceof ThirdPartyOrRecipientType) ? true : 'Must be an instance of ThirdPartyOrRecipientType.';
            }],
            ['thirdParty', function($value) {
                if ($value === null) return true;
                return ($value instanceof LegalPerson) ? true : 'Must be an instance of LegalPerson.';
            }],
            ['breakdown', function($value) {
                return ($value instanceof Breakdown) ? true : 'Must be an instance of Breakdown.';
            }],
            ['rectificationBreakdown', function($value) {
                if ($value === null) return true;
                return ($value instanceof RectificationBreakdown) ? true : 'Must be an instance of RectificationBreakdown.';
            }],
            ['recipients', function($value) {
                if (empty($value)) return true;
                foreach ($value as $recipient) {
                    if (!($recipient instanceof LegalPerson)) {
                        return 'All recipients must be instances of LegalPerson.';
                    }
                }
                return true;
            }],
            [['taxAmount', 'totalAmount'], function($value) {
                return (is_float($value) || is_int($value)) ? true : 'Must be a number.';
            }],
            ['operationDate', function($value) {
                if ($value === null) return true;
                // Checks for format YYYY-MM-DD (simple regex)
                return (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) ? true : 'Must be a valid date (YYYY-MM-DD).';
            }],
        ]);
    }

    /**
     * Helper method to rename a DOM element by creating a new one with the desired tag name
     * and copying all children and attributes. This works around PHP 8.3's read-only tagName property.
     * 
     * @param \DOMDocument $doc The document
     * @param \DOMElement $originalNode The original node to rename
     * @param string $newTagName The new tag name
     * @return \DOMElement The new element with the desired tag name
     */
    private function renameElement(\DOMDocument $doc, \DOMElement $originalNode, $newTagName)
    {
        // Create new element with correct tag name
        $newElement = $doc->createElement($newTagName);
        
        // Copy all child nodes
        foreach ($originalNode->childNodes as $child) {
            $newElement->appendChild($child->cloneNode(true));
        }
        
        // Copy all attributes
        foreach ($originalNode->attributes as $attr) {
            $newElement->setAttribute($attr->nodeName, $attr->nodeValue);
        }
        
        return $newElement;
    }

    /**
     * Serializes the invoice submission to XML.
     * 
     * @return \DOMDocument The root element of this model's XML representation
     * @throws \DOMException
     */
    public function toXml()
    {
        // Create the XML document
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Create root element: RegistroAlta
        $root = $doc->createElement('RegistroAlta');
        $doc->appendChild($root);

        // IDVersion (required, hardcoded as '1.0')
        $idVersion = $doc->createElement('IDVersion', '1.0');
        $root->appendChild($idVersion);

        // IDFactura (required)
        if (method_exists($this, 'getInvoiceId')) {
            $invoiceId = $this->getInvoiceId();
            if (method_exists($invoiceId, 'toXml')) {
                $originalNode = $invoiceId->toXml($doc);
                $idFacturaNode = $this->renameElement($doc, $originalNode, 'IDFactura');
                $root->appendChild($idFacturaNode);
            }
        }

        // RefExterna (optional)
        if (!empty($this->externalReference)) {
            $root->appendChild($doc->createElement('RefExterna', $this->externalReference));
        }

        // NombreRazonEmisor (required)
        $root->appendChild($doc->createElement('NombreRazonEmisor', $this->issuerName));

        // Subsanacion (optional)
        if (!empty($this->subsanacion)) {
            $root->appendChild($doc->createElement('Subsanacion', $this->subsanacion));
        }

        // RechazoPrevio (optional)
        if (!empty($this->previousRejection)) {
            $root->appendChild($doc->createElement('RechazoPrevio', $this->previousRejection));
        }

        // TipoFactura (required)
        $root->appendChild($doc->createElement('TipoFactura', $this->invoiceType));

        // TipoRectificativa (optional)
        if (!empty($this->rectificationType)) {
            $root->appendChild($doc->createElement('TipoRectificativa', $this->rectificationType));
        }

        // FacturasRectificadas (optional)
        $rectData = $this->getRectificationData();
        if (!empty($rectData['rectified'])) {
            $facturasRectificadas = $doc->createElement('FacturasRectificadas');
            foreach ($rectData['rectified'] as $rect) {
                $idFacturaRectificada = $doc->createElement('IDFacturaRectificada');
                $idFacturaRectificada->appendChild($doc->createElement('IDEmisorFactura', $rect['issuerNif']));
                $idFacturaRectificada->appendChild($doc->createElement('NumSerieFactura', $rect['seriesNumber']));
                $idFacturaRectificada->appendChild($doc->createElement('FechaExpedicionFactura', $rect['issueDate']));
                $facturasRectificadas->appendChild($idFacturaRectificada);
            }
            $root->appendChild($facturasRectificadas);
        }

        // FacturasSustituidas (optional)
        if (!empty($rectData['substituted'])) {
            $facturasSustituidas = $doc->createElement('FacturasSustituidas');
            foreach ($rectData['substituted'] as $subst) {
                $idFacturaSustituida = $doc->createElement('IDFacturaSustituida');
                $idFacturaSustituida->appendChild($doc->createElement('IDEmisorFactura', $subst['issuerNif']));
                $idFacturaSustituida->appendChild($doc->createElement('NumSerieFactura', $subst['seriesNumber']));
                $idFacturaSustituida->appendChild($doc->createElement('FechaExpedicionFactura', $subst['issueDate']));
                $facturasSustituidas->appendChild($idFacturaSustituida);
            }
            $root->appendChild($facturasSustituidas);
        }

        // ImporteRectificacion (optional)
        if (!empty($this->rectificationBreakdown) && method_exists($this->rectificationBreakdown, 'toXml')) {
            $originalNode = $this->rectificationBreakdown->toXml($doc);
            $importeRectificacionNode = $this->renameElement($doc, $originalNode, 'ImporteRectificacion');
            $root->appendChild($importeRectificacionNode);
        }

        // FechaOperacion (optional)
        if (!empty($this->operationDate)) {
            $root->appendChild($doc->createElement('FechaOperacion', $this->operationDate));
        }

        // DescripcionOperacion (required)
        $root->appendChild($doc->createElement('DescripcionOperacion', $this->operationDescription));

        // FacturaSimplificadaArt7273 (optional)
        if (!empty($this->simplifiedInvoice)) {
            $root->appendChild($doc->createElement('FacturaSimplificadaArt7273', $this->simplifiedInvoice));
        }

        // FacturaSinIdentifDestinatarioArt61d (optional)
        if (!empty($this->invoiceWithoutRecipient)) {
            $root->appendChild($doc->createElement('FacturaSinIdentifDestinatarioArt61d', $this->invoiceWithoutRecipient));
        }

        // Macrodato (optional)
        if (!empty($this->macrodata)) {
            $root->appendChild($doc->createElement('Macrodato', $this->macrodata));
        }

        // EmitidaPorTerceroODestinatario (optional)
        if (!empty($this->issuedBy)) {
            $root->appendChild($doc->createElement('EmitidaPorTerceroODestinatario', $this->issuedBy));
        }

        // Tercero (optional)
        if (!empty($this->thirdParty) && method_exists($this->thirdParty, 'toXml')) {
            $originalNode = $this->thirdParty->toXml($doc);
            $terceroNode = $this->renameElement($doc, $originalNode, 'Tercero');
            $root->appendChild($terceroNode);
        }

        // Destinatarios (optional)
        if (!empty($this->recipients)) {
            $destinatarios = $doc->createElement('Destinatarios');
            foreach ($this->recipients as $recipient) {
                if (method_exists($recipient, 'toXml')) {
                    $originalNode = $recipient->toXml($doc);
                    $idDestinatario = $this->renameElement($doc, $originalNode, 'IDDestinatario');
                    $destinatarios->appendChild($idDestinatario);
                }
            }
            $root->appendChild($destinatarios);
        }

        // Cupon (optional)
        if (!empty($this->coupon)) {
            $root->appendChild($doc->createElement('Cupon', $this->coupon));
        }

        // Desglose (required)
        if (!empty($this->breakdown) && method_exists($this->breakdown, 'toXml')) {
            $originalNode = $this->breakdown->toXml($doc);
            $desgloseNode = $this->renameElement($doc, $originalNode, 'Desglose');
            $root->appendChild($desgloseNode);
        }

        // CuotaTotal (required)
        $root->appendChild($doc->createElement('CuotaTotal', number_format($this->taxAmount, 2, '.', '')));

        // ImporteTotal (required)
        $root->appendChild($doc->createElement('ImporteTotal', number_format($this->totalAmount, 2, '.', '')));

        // Encadenamiento (required, must be set by the user)
        if (!empty($this->chaining) && method_exists($this->chaining, 'toXml')) {
            $originalNode = $this->chaining->toXml($doc);
            $encadenamientoNode = $this->renameElement($doc, $originalNode, 'Encadenamiento');
            $root->appendChild($encadenamientoNode);
        }

        // SistemaInformatico (required)
        if (!empty($this->computerSystem) && method_exists($this->computerSystem, 'toXml')) {
            $originalNode = $this->computerSystem->toXml($doc);
            $sistemaNode = $this->renameElement($doc, $originalNode, 'SistemaInformatico');
            $root->appendChild($sistemaNode);
        }

        // FechaHoraHusoGenRegistro (required, must be set by the user)
        if (!empty($this->generationDateTime)) {
            $root->appendChild($doc->createElement('FechaHoraHusoGenRegistro', $this->generationDateTime));
        }

        // NumRegistroAcuerdoFacturacion (optional)
        if (!empty($this->invoiceAgreementNumber)) {
            $root->appendChild($doc->createElement('NumRegistroAcuerdoFacturacion', $this->invoiceAgreementNumber));
        }

        // IdAcuerdoSistemaInformatico (optional)
        if (!empty($this->systemAgreementId)) {
            $root->appendChild($doc->createElement('IdAcuerdoSistemaInformatico', $this->systemAgreementId));
        }

        // TipoHuella (required, must be set by the user)
        if (!empty($this->hashType)) {
            $root->appendChild($doc->createElement('TipoHuella', $this->hashType));
        }

        // Huella (required, must be set by the user)
        if (!empty($this->hashValue)) {
            $root->appendChild($doc->createElement('Huella', $this->hashValue));
        }

        // ds:Signature (optional, to be added after signing)
        // Not included here, but the node can be appended externally if needed

        return $doc;
    }
}
