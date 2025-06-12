<?php
namespace eseperio\verifactu\models;

/**
 * Model representing a system event for submission to AEAT.
 * Based on: RegistroEvento (EventosSIF.xsd.xml)
 */
class EventRecord extends Model
{
    /**
     * Event version identifier (IDVersion)
     * @var string
     */
    public $versionId;

    /**
     * Event data (Evento)
     * @var array
     */
    public $eventData;

    /**
     * Returns validation rules for event record.
     * @return array
     */
    public function rules()
    {
        return [
            [['versionId', 'eventData'], 'required'],
            [['versionId'], 'string'],
            [['eventData'], 'array'],
        ];
    }
}
