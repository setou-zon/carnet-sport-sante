-- Export complet de la base Supabase (Carnet Sport Santé / Sport pour tous)
-- vers un fichier JSON à importer dans la version Laravel.
--
-- À exécuter dans Supabase → SQL Editor (projet carnet-sport-sante-ca).
-- Copier la valeur unique du résultat dans un fichier, par exemple export.json,
-- puis sur le serveur Laravel :   php artisan import:supabase export.json
--
-- ATTENTION : ce fichier contient des données de santé et les empreintes des mots
-- de passe. Ne pas l'envoyer par email ni WhatsApp ; le copier directement sur le
-- serveur, puis le supprimer après l'import.
select json_build_object(
  'version', 1,
  'exporte_le', now(),
  'users', (select coalesce(json_agg(json_build_object(
              'id', p.id, 'email', coalesce(p.email, u.email), 'password', u.encrypted_password,
              'nom', p.nom, 'role', p.role, 'actif', p.actif, 'niveau', p.niveau,
              'province_id', p.province_id, 'region_id', p.region_id, 'created_at', p.created_at)
            order by p.created_at), '[]'::json)
            from public.profiles p join auth.users u on u.id = p.id),
  'participants',  (select coalesce(json_agg(t order by t.created_at), '[]'::json) from public.participants t),
  'sites',         (select coalesce(json_agg(t order by t.created_at), '[]'::json) from public.sites t),
  'presences',     (select coalesce(json_agg(t order by t.created_at), '[]'::json) from public.presences t),
  'transmissions', (select coalesce(json_agg(t order by t.transmis_le), '[]'::json) from public.transmissions t),
  'compteur',      (select last_value from public.participant_code_seq)
) as export;
