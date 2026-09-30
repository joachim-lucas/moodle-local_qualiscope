## QualiScope — ChangeLog

### v1.0.2 (2026093000)

Corrections issues de la revue de code avant publication sur le Marketplace.

- Sécurité : contrôle de la capacité et du cours détenteur de l'enregistrement avant l'enregistrement d'une action corrective et le dépôt d'une preuve.
- Sécurité : téléversement des preuves via un formulaire Moodle avec types de fichiers et taille limités ; les fichiers ne sont plus servis en ligne.
- Sécurité : clé de session désormais exigée sur toutes les écritures (analyses de campagne, exports).
- Sécurité : l'export des fichiers de preuves de l'utilisateur n'inclut plus ceux des autres utilisateurs.
- Confidentialité : le fournisseur ne supprime plus les données de tout le site à la purge d'un contexte ; il agit uniquement sur le contexte reçu, gère les parcours de contexte et implémente l'interface userlist.
- Confidentialité : suppression des données d'un cours supprimé (résultats, actions, preuves et quota).
- Web services : ordre des arguments de `create_action` corrigé, la fonction était inutilisable.
- Web services : la page de progression d'une campagne utilise un service externe et `core/ajax` au lieu d'un appel XHR artisanal.
- Rendu : les pages restantes passent par des gabarits Mustache et l'API de sortie.
- Rendu : le rapport imprimable s'affiche dans le thème, ses règles living dans `styles.css`.
- Internationalisation : plus aucun texte utilisateur codé en dur, y compris en français.
- En-tête : fichiers de hook conformes à l'en-tête standard Moodle.

### v1.0.1 (2026092500)

- Gestion des campagnes d'audit : filtres avancés, recherche instantanée, barre de progression temps réel.
- Comparateur de campagnes et suivi de l'évolution temporelle des résultats.
- Plan d'action correctif consolidé (CAPA) avec création en masse des actions.
- Export des rapports d'audit en Excel, CSV, PDF et impression HTML ; pack d'évidences auditeur (PDF + ZIP).
- Analyse macro multi-cours par critères et indicateurs, repérage des points faibles prioritaires.
- Standards de référence : Qualiopi (indicateurs affinés), IACET, ISO 21001 ; pages d'aide par standard.
- Indicateurs 1 et 26 renforcés (accessibilité WCAG), notation de conformité non binaire avec alignement constructif et analyse de l'engagement.
- Licence intrinsèque à signature offline (clé de licence, hash de site, expiration, quota de cours gratuits).
- Internationalisation : traduction anglaise complète des critères, indicateurs et vérifications ; chargement automatique des helpers de localisation.
- Compatibilité Moodle 4.5 : rendu HTML du résumé de cours, fonds de cartes et widgets Bootstrap 4, chevron de sélection.
- CI : vérification du code (codechecker, stylelint) sur les matrices CI Moodle 5.2 et 4.5, première publication.