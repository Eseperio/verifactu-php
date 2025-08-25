<?php

declare(strict_types=1);

namespace eseperio\verifactu\models;

use eseperio\verifactu\models\enums\InvoiceType;
use eseperio\verifactu\models\enums\OperationQualificationType;
use eseperio\verifactu\models\enums\RectificationType;
use eseperio\verifactu\models\enums\ThirdPartyOrRecipientType;
use eseperio\verifactu\models\enums\YesNoType;

/**
 * Model representing an invoice submission ("Alta").
 * Based on: RegistroAlta (SuministroInformacion.xsd)
 * Original schema: RegistroAltaType.
 * @see docs/aeat/esquemas/SuministroInformacion.xsd
 */
class InvoiceSubmission extends InvoiceRecord
{
    const SF_NAMESPACE = "https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd";


    public $externalRef;
    /**
     * Issuer company or person name (NombreRazonEmisor)
     * Example: 'Acme Corp S.L.'.
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
     * Invoice type (TipoFactura).
     * @var InvoiceType
     */
    public $invoiceType;

    /**
     * Rectification type (TipoRectificativa, optional)
     * Only for rectifying invoices.
     * @var RectificationType|null
     */
    public $rectificationType;

    /**
     * Operation date (FechaOperacion, optional).
     * @var string|null
     */
    public $operationDate;

    /**
     * Operation description (DescripcionOperacion).
     * @var string
     */
    public $operationDescription;

    /**
     * Simplified invoice indicator (FacturaSimplificadaArt7273, optional).
     * @var YesNoType|null
     */
    public $simplifiedInvoice;

    /**
     * Invoice without recipient identification (FacturaSinIdentifDestinatarioArt61d, optional).
     * @var YesNoType|null
     */
    public $invoiceWithoutRecipient;

    /**
     * Macrodata indicator (Macrodato, optional).
     * @var YesNoType|null
     */
    public $macrodata;

    /**
     * Issued by third party or recipient (EmitidaPorTerceroODestinatario, optional).
     * @var ThirdPartyOrRecipientType|null
     */
    public $issuedBy;

    /**
     * Third party (Tercero, optional).
     * @var LegalPerson|null
     */
    private $thirdParty;

    /**
     * Recipients list (Destinatarios, optional).
     * @var LegalPerson[]
     */
    private $recipients = [];

    /**
     * Coupon indicator (Cupon, optional).
     * @var YesNoType|null
     */
    public $coupon;

    /**
     * Tax breakdown (Desglose).
     * @var Breakdown
     */
    private $breakdown;

    /**
     * Rectification breakdown (ImporteRectificacion, optional).
     * @var RectificationBreakdown|null
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
     * Invoice agreement registration number (NumRegistroAcuerdoFacturacion, optional).
     * @var string|null
     */
    public $invoiceAgreementNumber;

    /**
     * System agreement ID (IdAcuerdoSistemaInformatico, optional).
     * @var string|null
     */
    public $systemAgreementId;

    /**
     * Get the rectification data.
     * @return array
     */
    public function getRectificationData()
    {
        return $this->rectificationData;
    }

    /**
     * Set the rectification data.
     * @param array $rectificationData Array of rectification data
     * @return $this
     */
    public function setRectificationData($rectificationData): static
    {
        $this->rectificationData = $rectificationData;

        return $this;
    }

    /**
     * Add a rectified invoice.
     * @param string $issuerNif NIF of the original invoice issuer
     * @param string $seriesNumber Series and number of rectified invoice
     * @param string $issueDate Date of rectified invoice (YYYY-MM-DD)
     * @return $this
     */
    public function addRectifiedInvoice($issuerNif, $seriesNumber, $issueDate): static
    {
        if (!isset($this->rectificationData['rectified'])) {
            $this->rectificationData['rectified'] = [];
        }

        $this->rectificationData['rectified'][] = [
            'issuerNif' => $issuerNif,
            'seriesNumber' => $seriesNumber,
            'issueDate' => $issueDate,
        ];

        return $this;
    }

    /**
     * Add a substituted invoice.
     * @param string $issuerNif NIF of the original invoice issuer
     * @param string $seriesNumber Series and number of substituted invoice
     * @param string $issueDate Date of substituted invoice (YYYY-MM-DD)
     * @return $this
     */
    public function addSubstitutedInvoice($issuerNif, $seriesNumber, $issueDate): static
    {
        if (!isset($this->rectificationData['substituted'])) {
            $this->rectificationData['substituted'] = [];
        }

        $this->rectificationData['substituted'][] = [
            'issuerNif' => $issuerNif,
            'seriesNumber' => $seriesNumber,
            'issueDate' => $issueDate,
        ];

        return $this;
    }

    /**
     * Get the recipients list.
     * @return LegalPerson[]
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * Add a recipient to the invoice.
     * @param LegalPerson|array $recipient Recipient data
     * @return $this
     */
    public function addRecipient($recipient): static
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
     * Legacy method for backward compatibility.
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
     * Get the third party.
     * @return LegalPerson|null
     */
    public function getThirdParty()
    {
        return $this->thirdParty;
    }

    /**
     * Set the third party.
     * @param LegalPerson|array $thirdParty Third party data
     * @return $this
     */
    public function setThirdParty($thirdParty): static
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
     * Get the rectification breakdown.
     * @return RectificationBreakdown|null
     */
    public function getRectificationBreakdown()
    {
        return $this->rectificationBreakdown;
    }

    /**
     * Set the rectification breakdown.
     * @param RectificationBreakdown|array $rectificationBreakdown Rectification breakdown data
     * @return $this
     */
    public function setRectificationBreakdown($rectificationBreakdown): static
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
     * Get the tax breakdown.
     * @return Breakdown
     */
    public function getBreakdown()
    {
        return $this->breakdown;
    }

    /**
     * Add a tax breakdown detail.
     * @param BreakdownDetail|array $detail Breakdown detail
     * @return $this
     */
    public function addBreakdownDetail($detail): static
    {
        if ($this->breakdown === null) {
            $this->breakdown = new Breakdown();
        }

        $this->breakdown->addDetail($detail);

        return $this;
    }

    /**
     * Set the tax breakdown.
     * @param Breakdown|array $breakdown Tax breakdown
     * @return $this
     */
    public function setBreakdown($breakdown): static
    {
        if (is_array($breakdown)) {
            $breakdownObj = new Breakdown();

            if (isset($breakdown[0]) && is_array($breakdown[0])) {
                // Legacy format support - array of breakdown items
                foreach ($breakdown as $item) {
                    $detail = new BreakdownDetail();

                    if (isset($item['rate'])) {
                        $detail->taxRate = $item['rate'];
                    }

                    if (isset($item['base'])) {
                        $detail->taxableBase = $item['base'];
                    }

                    if (isset($item['amount'])) {
                        $detail->taxAmount = $item['amount'];
                    }

                    // Set default values for required fields
                    if ($detail->operationQualification === null && $detail->exemptOperation === null) {
                        $detail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;
                    }

                    $breakdownObj->addDetail($detail);
                }
            } elseif (isset($breakdown['details'])) {
                // Array with Breakdown properties
                $breakdownObj->setDetails($breakdown['details']);
            }

            $this->breakdown = $breakdownObj;
        } else {
            $this->breakdown = $breakdown;
        }

        return $this;
    }

    /**
     * Legacy method for backward compatibility.
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
        $detail->operationQualification = OperationQualificationType::SUBJECT_NO_EXEMPT_NO_REVERSE;

        return $this->addBreakdownDetail($detail);
    }

    /**
     * Returns validation rules for invoice submission.
     * Merges parent rules with specific rules for invoice submission.
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            [['issuerName', 'invoiceType', 'operationDescription', 'breakdown', 'taxAmount', 'totalAmount'], 'required'],
            [['issuerName', 'operationDescription', 'operationDate', 'invoiceAgreementNumber', 'systemAgreementId'], 'string'],
            ['invoiceType', fn($value): bool|string => ($value instanceof InvoiceType) ? true : 'Must be an instance of InvoiceType.'],
            ['rectificationType', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof RectificationType) ? true : 'Must be an instance of RectificationType.';
            }],
            ['simplifiedInvoice', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['invoiceWithoutRecipient', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['macrodata', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['coupon', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof YesNoType) ? true : 'Must be an instance of YesNoType.';
            }],
            ['issuedBy', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof ThirdPartyOrRecipientType) ? true : 'Must be an instance of ThirdPartyOrRecipientType.';
            }],
            ['thirdParty', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof LegalPerson) ? true : 'Must be an instance of LegalPerson.';
            }],
            ['breakdown', fn($value): bool|string => ($value instanceof Breakdown) ? true : 'Must be an instance of Breakdown.'],
            ['rectificationBreakdown', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                return ($value instanceof RectificationBreakdown) ? true : 'Must be an instance of RectificationBreakdown.';
            }],
            ['recipients', function ($value, $model): bool|string {
                $type = $model->invoiceType?->value ?? null;
                $sinIdentif = $model->invoiceWithoutRecipient?->value ?? null;
                if ($type === InvoiceType::STANDARD->value) {
                    if (empty($value)) {
                        return 'Recipient is required for STANDARD invoices (F1).';
                    }
                } elseif ($type === InvoiceType::SIMPLIFIED->value) {
                    if ($model->invoiceWithoutRecipient?->value === YesNoType::NO->value) {
                        // Allowed but not required
                    } else {
                        if (!empty($value)) {
                            return 'Recipient is not allowed for SIMPLIFIED invoices (F2) unless explicit identification.';
                        }
                    }
                } elseif ($type === InvoiceType::REPLACEMENT->value) {
                    if (empty($value)) {
                        return 'Recipient is required for REPLACEMENT invoices (F3).';
                    }
                } elseif (in_array($type, [
                    InvoiceType::RECTIFICATION_1->value,
                    InvoiceType::RECTIFICATION_2->value,
                    InvoiceType::RECTIFICATION_3->value,
                    InvoiceType::RECTIFICATION_4->value
                ])) {
                    if (empty($value)) {
                        return 'Recipient is required for RECTIFICATION invoices (R1-R4).';
                    }
                } elseif ($type === InvoiceType::RECTIFICATION_SIMPLIFIED->value) {
                    if (!empty($value)) {
                        return 'Recipient is not allowed for RECTIFICATION_SIMPLIFIED invoices (R5).';
                    }
                }
                if (!empty($value)) {
                    foreach ($value as $recipient) {
                        if (!($recipient instanceof LegalPerson)) {
                            return 'All recipients must be instances of LegalPerson.';
                        }
                    }
                }

                return true;
            }],
            [['taxAmount', 'totalAmount'], fn($value): bool|string => (is_float($value) || is_int($value)) ? true : 'Must be a number.'],
            ['operationDate', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                // Checks for format YYYY-MM-DD (simple regex)
                return (preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) ? true : 'Must be a valid date (YYYY-MM-DD).';
            }],
            // Nueva regla para validar el formato de la fecha de expedición
            ['invoiceId', function ($value): bool|string {
                if ($value === null) {
                    return true;
                }

                if (!property_exists($value, 'issueDate')) {
                    return true;
                }

                // Verifica el formato DD-MM-YYYY
                return (preg_match('/^\\d{2,2}-\\d{2,2}-\\d{4,4}$/', $value->issueDate))
                    ? true
                    : 'La fecha de expedición debe tener el formato DD-MM-YYYY.';
            }],
        ]);
    }

    /**
     * Helper method to rename a DOM element by creating a new one with the desired tag name
     * and copying all children and attributes. This works around PHP 8.3's read-only tagName property.
     *
     * @param \DOMDocument $doc The document
     * @param \DOMNode $originalNode The original node (can be DOMElement or DOMDocument)
     * @param string $newTagName The new tag name
     * @return \DOMElement The new element with the desired tag name
     */
    private function renameElement(\DOMDocument $doc, \DOMNode $originalNode, string $newTagName, ?string $namespace = null)
    {
        // Normaliza el nodo original a un DOMElement
        if ($originalNode instanceof \DOMDocument) {
            $originalNode = $originalNode->documentElement;
        }
        if (!($originalNode instanceof \DOMElement)) {
            // Si no es un elemento, crea uno vacío con el nombre nuevo
            if ($namespace) {
                return $doc->createElementNS($namespace, $newTagName);
            }
            return $doc->createElement($newTagName);
        }

        // Crear nuevo elemento con el nombre correcto y posible namespace
        if ($namespace) {
            $newElement = $doc->createElementNS($namespace, $newTagName);
        } else {
            $newElement = $doc->createElement($newTagName);
        }

        // Special handling for IDFactura - direct copy of child elements to ensure all fields are included
        if ($newTagName === 'sf:IDFactura') {
            if ($this->invoiceId) {
                if ($namespace) {
                    $newElement->appendChild($doc->createElementNS($namespace, 'sf:IDEmisorFactura', $this->invoiceId->issuerNif));
                    $newElement->appendChild($doc->createElementNS($namespace, 'sf:NumSerieFactura', $this->invoiceId->seriesNumber));
                    $newElement->appendChild($doc->createElementNS($namespace, 'sf:FechaExpedicionFactura', $this->invoiceId->issueDate));
                } else {
                    $newElement->appendChild($doc->createElement('sf:IDEmisorFactura', $this->invoiceId->issuerNif));
                    $newElement->appendChild($doc->createElement('sf:NumSerieFactura', $this->invoiceId->seriesNumber));
                    $newElement->appendChild($doc->createElement('sf:FechaExpedicionFactura', $this->invoiceId->issueDate));
                }
            }
            return $newElement;
        }

        // Copiar hijos importándolos al documento destino (importNode evita pérdidas entre documentos distintos)
        foreach ($originalNode->childNodes as $child) {
            $newElement->appendChild($doc->importNode($child, true));
        }

        // Copiar atributos (respetando namespaces si los hay)
        if ($originalNode->hasAttributes()) {
            foreach ($originalNode->attributes as $attr) {
                if ($attr->namespaceURI) {
                    $newElement->setAttributeNS($attr->namespaceURI, $attr->nodeName, $attr->nodeValue);
                } else {
                    $newElement->setAttribute($attr->nodeName, $attr->nodeValue);
                }
            }
        }

        return $newElement;
    }

    /**
     * Deprecated: Use InvoiceSerializer::toInvoiceXml() instead.
     * 
     * @deprecated This method has been replaced by InvoiceSerializer::toInvoiceXml()
     * @return \DOMDocument
     * @throws \Exception
     */
    public function toXml(): \DOMDocument
    {
        throw new \Exception(
            'This method is deprecated. Use InvoiceSerializer::toInvoiceXml() instead. ' .
            'The XML generation has been moved to the InvoiceSerializer service.'
        );
    }
}
