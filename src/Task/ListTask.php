<?php

namespace Sweetchuck\Robo\TemplateTask\Task;

class ListTask extends BaseTask
{
    /**
     * {@inheritdoc}
     */
    protected $taskName = 'RoboList';

    /**
     * {@inheritdoc}
     */
    protected $action = 'list';

    // region Options.

    // region Option - raw.

    /**
     * @var bool
     */
    protected $raw = false;

    public function getRaw(): bool
    {
        return $this->raw;
    }

    /**
     * @return $this
     */
    public function setRaw(bool $value)
    {
        $this->raw = $value;

        return $this;
    }
    // endregion

    // region Option - format.
    /**
     * @var string
     */
    protected $format = '';

    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @return $this
     */
    public function setFormat(string $value)
    {
        $this->format = $value;

        return $this;
    }
    // endregion

    // endregion

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        parent::setOptions($options);

        foreach ($options as $name => $value) {
            switch ($name) {
                case 'raw':
                    $this->setRaw($value);
                    break;

                case 'format':
                    $this->setFormat($value);
                    break;
            }
        }

        return $this;
    }

    protected function getCommandOptions(): array
    {
        return [
            'raw' => [
                'type' => 'flag',
                'value' => $this->getRaw(),
            ],
            'format' => [
                'type' => 'value',
                'value' => $this->getFormat(),
            ],
        ] + parent::getCommandOptions();
    }
}
