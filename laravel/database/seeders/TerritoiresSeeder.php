<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Découpage territorial officiel du Burkina Faso : 17 régions, 47 provinces.
 * Peut être relancé sans risque (mise à jour par identifiant).
 */
class TerritoiresSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            [1, 'Bankui', 'Dédougou'],
            [2, 'Djôrô', 'Gaoua'],
            [3, 'Goulmou', 'Fada N\'Gourma'],
            [4, 'Guiriko', 'Bobo-Dioulasso'],
            [5, 'Kadiogo', 'Ouagadougou'],
            [6, 'Kuilsé', 'Kaya'],
            [7, 'Liptako', 'Dori'],
            [8, 'Nakambé', 'Tenkodogo'],
            [9, 'Nando', 'Koudougou'],
            [10, 'Nazinon', 'Manga'],
            [11, 'Oubri', 'Ziniaré'],
            [12, 'Sirba', 'Bogandé'],
            [13, 'Soum', 'Djibo'],
            [14, 'Sourou', 'Tougan'],
            [15, 'Tannounyan', 'Banfora'],
            [16, 'Tapoa', 'Diapaga'],
            [17, 'Yaadga', 'Ouahigouya'],
        ];
        $provinces = [
            [1, 14, 'Kossi', 'Nouna'],
            [2, 14, 'Nayala', 'Toma'],
            [3, 14, 'Sourou', 'Tougan'],
            [4, 1, 'Mouhoun', 'Dédougou'],
            [5, 1, 'Balé', 'Boromo'],
            [6, 1, 'Bamwa', 'Solenzo'],
            [7, 4, 'Houet', 'Bobo-Dioulasso'],
            [8, 4, 'Kénédougou', 'Orodara'],
            [9, 4, 'Tuy', 'Houndé'],
            [10, 15, 'Comoé', 'Banfora'],
            [11, 15, 'Léraba', 'Sindou'],
            [12, 2, 'Bougouriba', 'Diébougou'],
            [13, 2, 'Ioba', 'Dano'],
            [14, 2, 'Noumbiel', 'Batié'],
            [15, 2, 'Poni', 'Gaoua'],
            [16, 17, 'Loroum', 'Titao'],
            [17, 17, 'Passoré', 'Yako'],
            [18, 17, 'Yatenga', 'Ouahigouya'],
            [19, 17, 'Zondoma', 'Gourcy'],
            [20, 9, 'Boulkiemdé', 'Koudougou'],
            [21, 9, 'Sanguié', 'Réo'],
            [22, 9, 'Sissili', 'Léo'],
            [23, 9, 'Ziro', 'Sapouy'],
            [24, 13, 'Djelgodji', 'Djibo'],
            [25, 13, 'Karo-Peli', 'Arbinda'],
            [26, 6, 'Bam', 'Kongoussi'],
            [27, 6, 'Namentenga', 'Boulsa'],
            [28, 6, 'Sandbondtenga', 'Kaya'],
            [29, 11, 'Bassitenga', 'Ziniaré'],
            [30, 11, 'Ganzourgou', 'Zorgho'],
            [31, 11, 'Kourwéogo', 'Boussé'],
            [32, 5, 'Kadiogo', 'Ouagadougou'],
            [33, 10, 'Bazèga', 'Kombissiri'],
            [34, 10, 'Nahouri', 'Pô'],
            [35, 10, 'Zoundwéogo', 'Manga'],
            [36, 8, 'Boulgou', 'Tenkodogo'],
            [37, 8, 'Koulpelogo', 'Ouargaye'],
            [38, 8, 'Kourittenga', 'Koupèla'],
            [39, 7, 'Oudalan', 'Gorom-Gorom'],
            [40, 7, 'Séno', 'Dori'],
            [41, 7, 'Yagha', 'Sebba'],
            [42, 3, 'Gourma', 'Fada N\'Gourma'],
            [43, 3, 'Kompienga', 'Pama'],
            [44, 16, 'Dyamongou', 'Kantchari'],
            [45, 16, 'Gobnangou', 'Diapaga'],
            [46, 12, 'Gnagna', 'Bogandé'],
            [47, 12, 'Komondjari', 'Gayéri'],
        ];

        DB::table('regions')->upsert(
            array_map(fn ($r) => ['id' => $r[0], 'nom' => $r[1], 'chef_lieu' => $r[2]], $regions),
            ['id'], ['nom', 'chef_lieu']
        );
        DB::table('provinces')->upsert(
            array_map(fn ($p) => ['id' => $p[0], 'region_id' => $p[1], 'nom' => $p[2], 'chef_lieu' => $p[3]], $provinces),
            ['id'], ['region_id', 'nom', 'chef_lieu']
        );
    }
}
