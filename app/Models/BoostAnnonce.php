<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoostAnnonce extends Model
{
    protected $table = 'boosts_annonces';

    protected $fillable = [
        'user_id',
        'annonce_id',
        'pack_type',
        'duree_jours',
        'montant',
        'reference_transaction',
        'statut_paiement',
        'methode_paiement',
        'devise',
        'payeur',
        'date_debut',
        'date_fin',
    ];

    protected function casts(): array
    {
        return [
            'duree_jours' => 'integer',
            'montant' => 'integer',
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function annonce()
    {
        return $this->belongsTo(Annonce::class);
    }
}

