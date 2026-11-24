<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Annonce extends Model
{
    protected $fillable = [
        'user_id',
        'titre',
        'description',
        'quartier',
        'universite_id',
        'loyer',
        'caution',
        'source_eau',
        'type_electricite',
        'lien_google_maps',
        'type_annonce',
        'type_chambre',
        'nombre_places',
        'statut_disponibilite',
        'statut_publication',
        'vues_count',
        'est_boostee',
        'boostee_jusqu_a',
    ];

    protected function casts(): array
    {
        return [
            'loyer' => 'integer',
            'caution' => 'decimal:2',
            'nombre_places' => 'integer',
            'vues_count' => 'integer',
            'est_boostee' => 'boolean',
            'boostee_jusqu_a' => 'datetime',
        ];
    }

    public function isCurrentlyBoosted(): bool
    {
        return (bool) ($this->boostee_jusqu_a && $this->boostee_jusqu_a->isFuture());
    }

    public function boosts()
    {
        return $this->hasMany(BoostAnnonce::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function universite()
    {
        return $this->belongsTo(Universite::class);
    }

    public function medias()
    {
        return $this->hasMany(MediaAnnonce::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }
}
