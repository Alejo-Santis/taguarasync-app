<?php

namespace Database\Seeders;

use App\Models\DianDiscrepancyReason;
use App\Models\DianFiscalResponsibility;
use App\Models\DianIdentificationType;
use App\Models\DianOrganizationType;
use App\Models\DianPaymentMethod;
use App\Models\DianRegimeType;
use App\Models\DianTaxType;
use App\Models\DianUnitMeasure;
use Illuminate\Database\Seeder;

class DianCatalogsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIdentificationTypes();
        $this->seedOrganizationTypes();
        $this->seedRegimeTypes();
        $this->seedFiscalResponsibilities();
        $this->seedTaxTypes();
        $this->seedPaymentMethods();
        $this->seedUnitMeasures();
        $this->seedDiscrepancyReasons();
    }

    private function seedIdentificationTypes(): void
    {
        $types = [
            ['code' => '11', 'name' => 'Registro civil', 'is_active' => true],
            ['code' => '12', 'name' => 'Tarjeta de identidad', 'is_active' => true],
            ['code' => '13', 'name' => 'Cédula de ciudadanía', 'is_active' => true],
            ['code' => '21', 'name' => 'Tarjeta de extranjería', 'is_active' => true],
            ['code' => '22', 'name' => 'Cédula de extranjería', 'is_active' => true],
            ['code' => '31', 'name' => 'NIT', 'is_active' => true],
            ['code' => '41', 'name' => 'Pasaporte', 'is_active' => true],
            ['code' => '42', 'name' => 'Documento de identificación extranjero', 'is_active' => true],
            ['code' => '43', 'name' => 'Sin identificación del exterior o para uso definido por la DIAN', 'is_active' => true],
            ['code' => '44', 'name' => 'Documento de identificación extranjero persona natural', 'is_active' => true],
            ['code' => '46', 'name' => 'NIUP (Número de Identificación único de Personas)', 'is_active' => true],
            ['code' => '47', 'name' => 'PEP (Permiso Especial de Permanencia)', 'is_active' => true],
            ['code' => '48', 'name' => 'PPT (Permiso por Protección Temporal)', 'is_active' => true],
            ['code' => '50', 'name' => 'NIT de otro país', 'is_active' => true],
            ['code' => '91', 'name' => 'NUIP', 'is_active' => true],
        ];

        foreach ($types as $type) {
            DianIdentificationType::firstOrCreate(['code' => $type['code']], $type);
        }
    }

    private function seedOrganizationTypes(): void
    {
        $types = [
            ['code' => '1', 'name' => 'Persona jurídica y asimiladas'],
            ['code' => '2', 'name' => 'Persona natural y asimiladas'],
        ];

        foreach ($types as $type) {
            DianOrganizationType::firstOrCreate(['code' => $type['code']], $type);
        }
    }

    private function seedRegimeTypes(): void
    {
        $types = [
            ['code' => '48', 'name' => 'Responsable de IVA'],
            ['code' => '49', 'name' => 'No responsable de IVA'],
        ];

        foreach ($types as $type) {
            DianRegimeType::firstOrCreate(['code' => $type['code']], $type);
        }
    }

    private function seedFiscalResponsibilities(): void
    {
        $responsibilities = [
            ['code' => 'O-07', 'name' => 'Gran contribuyente'],
            ['code' => 'O-09', 'name' => 'Autorretenedor de renta'],
            ['code' => 'O-10', 'name' => 'Agente de retención en el impuesto sobre las ventas'],
            ['code' => 'O-11', 'name' => 'Vigilado Superintendencia de Sociedades'],
            ['code' => 'O-12', 'name' => 'Obligado a llevar contabilidad'],
            ['code' => 'O-13', 'name' => 'Gran contribuyente'],
            ['code' => 'O-14', 'name' => 'Informante de exógena'],
            ['code' => 'O-15', 'name' => 'Autorretenedor'],
            ['code' => 'O-16', 'name' => 'Obligado a llevar contabilidad'],
            ['code' => 'O-17', 'name' => 'Obligado a facturar'],
            ['code' => 'O-18', 'name' => 'Obligado a facturar electrónicamente'],
            ['code' => 'O-19', 'name' => 'Con NIT'],
            ['code' => 'O-20', 'name' => 'Con registro mercantil'],
            ['code' => 'O-21', 'name' => 'Responsable del régimen común'],
            ['code' => 'O-23', 'name' => 'Facturación electrónica voluntaria'],
            ['code' => 'O-24', 'name' => 'Importador'],
            ['code' => 'O-25', 'name' => 'Exportador'],
            ['code' => 'O-26', 'name' => 'Vinculado económico'],
            ['code' => 'R-99-PN', 'name' => 'No aplica – Persona natural'],
        ];

        foreach ($responsibilities as $r) {
            DianFiscalResponsibility::firstOrCreate(['code' => $r['code']], $r);
        }
    }

    private function seedTaxTypes(): void
    {
        $taxes = [
            ['code' => '01', 'name' => 'IVA', 'default_rate' => 19.00, 'description' => 'Impuesto sobre las ventas'],
            ['code' => '02', 'name' => 'IC', 'default_rate' => null, 'description' => 'Impuesto al consumo'],
            ['code' => '03', 'name' => 'ICA', 'default_rate' => null, 'description' => 'Impuesto de industria y comercio'],
            ['code' => '04', 'name' => 'INC', 'default_rate' => null, 'description' => 'Impuesto nacional al consumo'],
            ['code' => '05', 'name' => 'ReteIVA', 'default_rate' => null, 'description' => 'Retención en la fuente por IVA'],
            ['code' => '06', 'name' => 'ReteFuente', 'default_rate' => null, 'description' => 'Retención en la fuente'],
            ['code' => '07', 'name' => 'ReteICA', 'default_rate' => null, 'description' => 'Retención de ICA'],
            ['code' => '20', 'name' => 'Impuesto bolsas plásticas', 'default_rate' => null, 'description' => 'Impuesto al consumo de bolsas plásticas'],
            ['code' => 'ZA', 'name' => 'No aplica', 'default_rate' => 0.00, 'description' => 'No aplica ningún impuesto'],
        ];

        foreach ($taxes as $tax) {
            DianTaxType::firstOrCreate(['code' => $tax['code']], $tax);
        }
    }

    private function seedPaymentMethods(): void
    {
        $methods = [
            ['code' => '1', 'name' => 'Efectivo'],
            ['code' => '2', 'name' => 'Consignación bancaria'],
            ['code' => '10', 'name' => 'Tarjeta de crédito'],
            ['code' => '20', 'name' => 'Cheque'],
            ['code' => '30', 'name' => 'Crédito ACH'],
            ['code' => '42', 'name' => 'Pago por compensación'],
            ['code' => '47', 'name' => 'Transferencia bancaria'],
            ['code' => '48', 'name' => 'Tarjeta débito'],
            ['code' => '49', 'name' => 'Nota bancaria'],
            ['code' => '71', 'name' => 'Bonos'],
            ['code' => '72', 'name' => 'Vales'],
            ['code' => 'ZZZ', 'name' => 'Otro medio de pago'],
        ];

        foreach ($methods as $method) {
            DianPaymentMethod::firstOrCreate(['code' => $method['code']], $method);
        }
    }

    private function seedUnitMeasures(): void
    {
        $units = [
            ['code' => '70', 'name' => 'Unidad', 'description' => 'Unidad básica'],
            ['code' => '94', 'name' => 'Pieza', 'description' => 'Each (EA)'],
            ['code' => 'NIU', 'name' => 'Unidad (NIU)', 'description' => 'Unidad por DIAN'],
            ['code' => '80', 'name' => 'Kilogramo', 'description' => 'Kilogramo (kg)'],
            ['code' => '85', 'name' => 'Gramo', 'description' => 'Gramo (g)'],
            ['code' => 'GRM', 'name' => 'Gramo (GRM)', 'description' => 'Gramo por DIAN'],
            ['code' => '10', 'name' => 'Mililitro', 'description' => 'Mililitro (ml)'],
            ['code' => '22', 'name' => 'Litro', 'description' => 'Litro (l)'],
            ['code' => '01', 'name' => 'Acre', 'description' => null],
            ['code' => '04', 'name' => 'Ampere', 'description' => null],
            ['code' => '11', 'name' => 'Centímetro cúbico', 'description' => null],
            ['code' => '14', 'name' => 'Centímetro', 'description' => null],
            ['code' => '25', 'name' => 'Metro', 'description' => null],
            ['code' => '26', 'name' => 'Metro cuadrado', 'description' => null],
            ['code' => '35', 'name' => 'Metro cúbico', 'description' => null],
            ['code' => 'BX', 'name' => 'Caja', 'description' => 'Box'],
            ['code' => 'PK', 'name' => 'Paquete', 'description' => 'Package'],
        ];

        foreach ($units as $unit) {
            DianUnitMeasure::firstOrCreate(['code' => $unit['code']], $unit);
        }
    }

    private function seedDiscrepancyReasons(): void
    {
        $creditNoteReasons = [
            ['code' => '1', 'applies_to' => 'credit_note', 'name' => 'Devolución de parte de los bienes; no aceptación de partes del servicio'],
            ['code' => '2', 'applies_to' => 'credit_note', 'name' => 'Anulación de factura electrónica'],
            ['code' => '3', 'applies_to' => 'credit_note', 'name' => 'Rebaja total aplicada'],
            ['code' => '4', 'applies_to' => 'credit_note', 'name' => 'Descuento total aplicado'],
            ['code' => '5', 'applies_to' => 'credit_note', 'name' => 'Rescisión: nulidad por falta de requisitos'],
            ['code' => '6', 'applies_to' => 'credit_note', 'name' => 'Otros'],
        ];

        $debitNoteReasons = [
            ['code' => '1', 'applies_to' => 'debit_note', 'name' => 'Intereses'],
            ['code' => '2', 'applies_to' => 'debit_note', 'name' => 'Gastos por cobrar'],
            ['code' => '3', 'applies_to' => 'debit_note', 'name' => 'Cambio del valor'],
            ['code' => '4', 'applies_to' => 'debit_note', 'name' => 'Otros'],
        ];

        foreach ([...$creditNoteReasons, ...$debitNoteReasons] as $reason) {
            DianDiscrepancyReason::firstOrCreate(
                ['code' => $reason['code'], 'applies_to' => $reason['applies_to']],
                $reason
            );
        }
    }
}
