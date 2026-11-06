<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Pruebas')]
final class CitasTest extends TestCase
{
    private function existeCitaDuplicada(array $citas, array $nuevaCita): bool
    {
        foreach ($citas as $cita) {
            if (
                $cita['medico_id'] === $nuevaCita['medico_id']
                && $cita['fecha'] === $nuevaCita['fecha']
                && $cita['hora'] === $nuevaCita['hora']
            ) {
                return true;
            }
        }

        return false;
    }

    #[TestDox('Fecha: Pasó')]
    public function testDetectaCitaDuplicada(): void
    {
        $citas = [
            [
                'medico_id' => 1,
                'fecha' => '2026-10-26',
                'hora' => '10:00:00',
            ],
        ];

        $nuevaCita = [
            'medico_id' => 1,
            'fecha' => '2026-10-26',
            'hora' => '10:00:00',
        ];

        $this->assertTrue(
            $this->existeCitaDuplicada($citas, $nuevaCita)
        );
    }

    #[TestDox('Médico: Pasó')]
    public function testPermiteMismoHorarioConOtroMedico(): void
    {
        $citas = [
            [
                'medico_id' => 1,
                'fecha' => '2026-10-26',
                'hora' => '10:00:00',
            ],
        ];

        $nuevaCita = [
            'medico_id' => 2,
            'fecha' => '2026-10-26',
            'hora' => '10:00:00',
        ];

        $this->assertFalse(
            $this->existeCitaDuplicada($citas, $nuevaCita)
        );
    }

    #[TestDox('Horario: Pasó')]
    public function testPermiteOtraHoraParaElMismoMedico(): void
    {
        $citas = [
            [
                'medico_id' => 1,
                'fecha' => '2026-10-26',
                'hora' => '10:00:00',
            ],
        ];

        $nuevaCita = [
            'medico_id' => 1,
            'fecha' => '2026-10-26',
            'hora' => '11:00:00',
        ];

        $this->assertFalse(
            $this->existeCitaDuplicada($citas, $nuevaCita)
        );
    }

    private function esFechaValidaParaCita(string $fecha, string $fechaActual): bool
    {
        $fechaObjeto = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($fechaObjeto === false || $fechaObjeto->format('Y-m-d') !== $fecha) {
            return false;
        }

        return $fecha >= $fechaActual;
    }

    #[TestDox('Fecha pasada: Pasó')]
    public function testRechazaCitasEnFechasPasadas(): void
    {
        $fechaPasada = '2020-01-01';
        $fechaHoy = '2026-10-25';

        $this->assertFalse(
            $this->esFechaValidaParaCita($fechaPasada, $fechaHoy)
        );
    }
}