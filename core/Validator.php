<?php

class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }

    private function val(string $field)
    {
        return $this->data[$field] ?? null;
    }

    public function required(string $field, string $label): self
    {
        $v = $this->val($field);
        if ($v === null || (is_string($v) && trim($v) === '')) {
            $this->errors[$field][] = "$label is required.";
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $v = $this->val($field);
        if ($v !== null && $v !== '' && !valid_email($v)) {
            $this->errors[$field][] = "$label must be a valid email address.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self
    {
        $v = $this->val($field);
        if ($v !== null && mb_strlen((string)$v) > $max) {
            $this->errors[$field][] = "$label must not exceed $max characters.";
        }
        return $this;
    }

    public function numeric(string $field, string $label): self
    {
        $v = $this->val($field);
        if ($v !== null && $v !== '' && !is_numeric($v)) {
            $this->errors[$field][] = "$label must be a number.";
        }
        return $this;
    }

    public function date(string $field, string $label): self
    {
        $v = $this->val($field);
        if ($v !== null && $v !== '' && strtotime($v) === false) {
            $this->errors[$field][] = "$label must be a valid date.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label): self
    {
        $v = $this->val($field);
        if ($v !== null && $v !== '' && !in_array($v, $allowed, true)) {
            $this->errors[$field][] = "$label is invalid.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0];
        }
        return null;
    }
}
