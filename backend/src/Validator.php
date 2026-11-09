<?php
declare(strict_types=1);

namespace Soportix;

final class Validator
{
    private array $errors = [];
    private array $clean = [];

    public function __construct(private readonly array $data)
    {
    }

    public function string(string $field, int $min, int $max, bool $required = true): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[$field] = 'Obligatorio';
            }
            return $this;
        }
        if (!is_string($value)) {
            $this->errors[$field] = 'Debe ser texto';
            return $this;
        }
        $value = trim($value);
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            $this->errors[$field] = "Debe tener entre $min y $max caracteres";
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function email(string $field): self
    {
        $this->string($field, 3, 190);
        if (isset($this->clean[$field])) {
            if (filter_var($this->clean[$field], FILTER_VALIDATE_EMAIL) === false) {
                $this->errors[$field] = 'Correo no válido';
                unset($this->clean[$field]);
            } else {
                $this->clean[$field] = strtolower($this->clean[$field]);
            }
        }
        return $this;
    }

    public function in(string $field, array $allowed, bool $required = true): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null) {
            if ($required) {
                $this->errors[$field] = 'Obligatorio';
            }
            return $this;
        }
        if (!in_array($value, $allowed, true)) {
            $this->errors[$field] = 'Valor no permitido';
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function id(string $field): self
    {
        if (!array_key_exists($field, $this->data)) {
            return $this;
        }
        $value = $this->data[$field];
        if ($value !== null && (!is_int($value) || $value < 1)) {
            $this->errors[$field] = 'Identificador no válido';
            return $this;
        }
        $this->clean[$field] = $value;
        return $this;
    }

    public function validated(): array
    {
        if ($this->errors) {
            throw new HttpError(422, 'Datos no válidos', $this->errors);
        }
        return $this->clean;
    }
}
