<?php

use PHPUnit\Framework\TestCase;

final class CitasTest extends TestCase
{
    public function testDetectaDosCitasDelMismoMedicoEnElMismoHorario(): void
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

        $horarioOcupado = false;

        foreach ($citas as $cita) {
            if (
                $cita['medico_id'] === $nuevaCita['medico_id']
                && $cita['fecha'] === $nuevaCita['fecha']
                && $cita['hora'] === $nuevaCita['hora']
            ) {
                $horarioOcupado = true;
                break;
            }
        }

        $this->assertTrue($horarioOcupado);
    }
}