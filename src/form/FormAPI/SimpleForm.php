<?php

declare(strict_types = 1);

namespace pocketmine\form\FormAPI;

use pocketmine\player\Player;

class SimpleForm extends IForm {

    const IMAGE_TYPE_PATH = 0;
    const IMAGE_TYPE_URL = 1;
    const IMAGE_TYPE_NONE = -1;

    /** @var string */
    private string $content = "";

    /** @var array<int, array{button: array<string, mixed>, label: string}> */
    private array $buttons = [];

    /**
     * @param callable(Player, mixed): void|null $callable
     */
    public function __construct(?callable $callable) {
        parent::__construct($callable);
        $this->data = [
            "type" => "form",
            "title" => "",
            "content" => $this->content,
            "buttons" => []
        ];
    }

    public function processData(&$data) : void {
        if ($data !== null) {
            if (!is_int($data)) {
                throw new FormValidationException("Expected an integer response, got " . gettype($data));
            }
            if (!isset($this->buttons[$data])) {
                throw new FormValidationException("Button $data does not exist");
            }
            $data = $this->buttons[$data]['label'] ?? null;
        }
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle(string $title) : self {
        $this->data["title"] = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle() : string {
        return $this->data["title"];
    }

    /**
     * @return string
     */
    public function getContent() : string {
        return $this->data["content"];
    }

    /**
     * @param string $content
     * @return $this
     */
    public function setContent(string $content) : self {
        $this->data["content"] = $content;
        return $this;
    }

    /**
     * Add a button to the form.
     *
     * @param string $text
     * @param int $imageType
     * @param string $imagePath
     * @param string|null $label
     * @return $this
     */
    public function addButton(string $text, int $imageType = self::IMAGE_TYPE_NONE, string $imagePath = "", ?string $label = null) : self {
        $button = ["text" => $text];

        if ($imageType !== self::IMAGE_TYPE_NONE) {
            $button["image"] = [
                "type" => $imageType === self::IMAGE_TYPE_PATH ? "path" : "url",
                "data" => $imagePath
            ];
        }

        $this->buttons[] = ["button" => $button, "label" => $label ?? (string)count($this->buttons)];
        $this->data["buttons"] = array_column($this->buttons, 'button');
        
        return $this;
    }
}