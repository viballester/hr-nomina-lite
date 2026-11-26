<?php

// Mensajes de validación en español. Solo las reglas que usa la API;
// cualquier otra cae en el idioma de respaldo (APP_FALLBACK_LOCALE=en).
return [
    'after_or_equal' => 'El campo :attribute debe ser una fecha igual o posterior a :date.',
    'between' => [
        'numeric' => 'El campo :attribute debe estar entre :min y :max.',
    ],
    'date' => 'El campo :attribute debe ser una fecha válida.',
    'email' => 'El campo :attribute debe ser un email válido.',
    'exists' => 'El :attribute seleccionado no es válido.',
    'in' => 'El :attribute seleccionado no es válido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'string' => 'El campo :attribute debe ser un texto.',
    'unique' => 'Este :attribute ya está registrado.',

    // Nombres de campo legibles para sustituir :attribute
    'attributes' => [
        'name' => 'nombre',
        'email' => 'email',
        'password' => 'contraseña',
        'department_id' => 'departamento',
        'position_id' => 'puesto',
        'hire_date' => 'fecha de alta',
        'status' => 'estado',
        'leave_type_id' => 'tipo de ausencia',
        'start_date' => 'fecha de inicio',
        'end_date' => 'fecha de fin',
        'year' => 'año',
        'month' => 'mes',
    ],
];
