<?php

namespace Database\Seeders;

use App\Models\ActiveIngredient;
use Illuminate\Database\Seeder;

class ActiveIngredientsSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = [
            ['dci_name' => 'Acetaminofen', 'pharmacological_group' => 'Analgesicos y antipireticos', 'atc_classification' => 'N02BE01'],
            ['dci_name' => 'Ibuprofeno', 'pharmacological_group' => 'AINES', 'atc_classification' => 'M01AE01'],
            ['dci_name' => 'Diclofenaco', 'pharmacological_group' => 'AINES', 'atc_classification' => 'M01AB05'],
            ['dci_name' => 'Naproxeno', 'pharmacological_group' => 'AINES', 'atc_classification' => 'M01AE02'],
            ['dci_name' => 'Meloxicam', 'pharmacological_group' => 'AINES', 'atc_classification' => 'M01AC06'],
            ['dci_name' => 'Amoxicilina', 'pharmacological_group' => 'Antibioticos betalactamicos', 'atc_classification' => 'J01CA04'],
            ['dci_name' => 'Azitromicina', 'pharmacological_group' => 'Macrolidos', 'atc_classification' => 'J01FA10'],
            ['dci_name' => 'Ciprofloxacina', 'pharmacological_group' => 'Fluoroquinolonas', 'atc_classification' => 'J01MA02'],
            ['dci_name' => 'Metronidazol', 'pharmacological_group' => 'Nitroimidazoles', 'atc_classification' => 'J01XD01'],
            ['dci_name' => 'Cefalexina', 'pharmacological_group' => 'Cefalosporinas primera generacion', 'atc_classification' => 'J01DB01'],
            ['dci_name' => 'Loratadina', 'pharmacological_group' => 'Antihistaminicos H1', 'atc_classification' => 'R06AX13'],
            ['dci_name' => 'Cetirizina', 'pharmacological_group' => 'Antihistaminicos H1', 'atc_classification' => 'R06AE07'],
            ['dci_name' => 'Desloratadina', 'pharmacological_group' => 'Antihistaminicos H1', 'atc_classification' => 'R06AX27'],
            ['dci_name' => 'Omeprazol', 'pharmacological_group' => 'Inhibidores bomba de protones', 'atc_classification' => 'A02BC01'],
            ['dci_name' => 'Pantoprazol', 'pharmacological_group' => 'Inhibidores bomba de protones', 'atc_classification' => 'A02BC02'],
            ['dci_name' => 'Ranitidina', 'pharmacological_group' => 'Antagonistas H2', 'atc_classification' => 'A02BA02'],
            ['dci_name' => 'Metformina', 'pharmacological_group' => 'Biguanidas antidiabeticos', 'atc_classification' => 'A10BA02'],
            ['dci_name' => 'Glibenclamida', 'pharmacological_group' => 'Sulfonilureas', 'atc_classification' => 'A10BB01'],
            ['dci_name' => 'Enalapril', 'pharmacological_group' => 'IECA antihipertensivos', 'atc_classification' => 'C09AA02'],
            ['dci_name' => 'Losartan', 'pharmacological_group' => 'ARA II antihipertensivos', 'atc_classification' => 'C09CA01'],
            ['dci_name' => 'Amlodipino', 'pharmacological_group' => 'Bloqueadores calcio', 'atc_classification' => 'C08CA01'],
            ['dci_name' => 'Atorvastatina', 'pharmacological_group' => 'Estatinas', 'atc_classification' => 'C10AA05'],
            ['dci_name' => 'Simvastatina', 'pharmacological_group' => 'Estatinas', 'atc_classification' => 'C10AA01'],
            ['dci_name' => 'Metoprolol', 'pharmacological_group' => 'Betabloqueadores', 'atc_classification' => 'C07AB02'],
            ['dci_name' => 'Salbutamol', 'pharmacological_group' => 'Broncodilatadores beta2', 'atc_classification' => 'R03AC02'],
            ['dci_name' => 'Budesonida', 'pharmacological_group' => 'Corticosteroides inhalados', 'atc_classification' => 'R03BA02'],
            ['dci_name' => 'Clonazepam', 'pharmacological_group' => 'Benzodiacepinas', 'atc_classification' => 'N03AE01'],
            ['dci_name' => 'Alprazolam', 'pharmacological_group' => 'Benzodiacepinas', 'atc_classification' => 'N05BA12'],
            ['dci_name' => 'Lorazepam', 'pharmacological_group' => 'Benzodiacepinas', 'atc_classification' => 'N05BA06'],
            ['dci_name' => 'Levotiroxina', 'pharmacological_group' => 'Hormonas tiroideas', 'atc_classification' => 'H03AA01'],
            ['dci_name' => 'Fluconazol', 'pharmacological_group' => 'Antifungicos azolicos', 'atc_classification' => 'J02AC01'],
            ['dci_name' => 'Ketoconazol', 'pharmacological_group' => 'Antifungicos azolicos', 'atc_classification' => 'J02AB02'],
            ['dci_name' => 'Albendazol', 'pharmacological_group' => 'Antiparasitarios', 'atc_classification' => 'P02CA03'],
            ['dci_name' => 'Mebendazol', 'pharmacological_group' => 'Antiparasitarios', 'atc_classification' => 'P02CA01'],
            ['dci_name' => 'Vitamina C (acido ascorbico)', 'pharmacological_group' => 'Vitaminas', 'atc_classification' => 'A11GA01'],
            ['dci_name' => 'Vitamina D3 (colecalciferol)', 'pharmacological_group' => 'Vitaminas', 'atc_classification' => 'A11CC05'],
            ['dci_name' => 'Acido folico', 'pharmacological_group' => 'Vitaminas', 'atc_classification' => 'B03BB01'],
            ['dci_name' => 'Hierro (sulfato ferroso)', 'pharmacological_group' => 'Antianemicos', 'atc_classification' => 'B03AA07'],
            ['dci_name' => 'Calcio carbonato', 'pharmacological_group' => 'Suplementos minerales', 'atc_classification' => 'A12AA04'],
            ['dci_name' => 'Tramadol', 'pharmacological_group' => 'Analgesicos opioides', 'atc_classification' => 'N02AX02'],
        ];

        foreach ($ingredients as $ingredient) {
            ActiveIngredient::firstOrCreate(
                ['dci_name' => $ingredient['dci_name']],
                $ingredient
            );
        }
    }
}
