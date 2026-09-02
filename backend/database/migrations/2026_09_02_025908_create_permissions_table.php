<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fixed catalog of grantable rights. Unlike roles (which a
     * residence can freely re-map via role_permissions), this list itself
     * is not admin-editable — adding a new permission is a code change.
     */
    private const CATALOG = [
        ['key' => 'cotisations.modifier', 'label' => 'Cotisations et paiements — ajouter/modifier/supprimer', 'group' => 'financier'],
        ['key' => 'depenses.modifier', 'label' => 'Dépenses — ajouter/modifier/supprimer', 'group' => 'financier'],
        ['key' => 'recettes.modifier', 'label' => 'Recettes — ajouter/modifier/supprimer', 'group' => 'financier'],
        ['key' => 'immeubles.gerer', 'label' => 'Immeubles — gérer', 'group' => 'structure'],
        ['key' => 'types_lot.gerer', 'label' => 'Types de lot et tarifs — gérer', 'group' => 'structure'],
        ['key' => 'appartements.gerer', 'label' => 'Appartements et propriétaires — gérer', 'group' => 'structure'],
    ];

    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('group');
            $table->timestamps();
        });

        $now = now();
        DB::table('permissions')->insert(array_map(
            fn (array $permission) => [...$permission, 'created_at' => $now, 'updated_at' => $now],
            self::CATALOG,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
