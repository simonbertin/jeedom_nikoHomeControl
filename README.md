# Plugin jeedom_nikoHomeControl

# Plugin Niko Home Control II pour Jeedom

## Description

Ce plugin permet d'intégrer votre installation Niko Home Control II dans Jeedom via l'API Hobby. Il supporte actuellement le contrôle des volets et stores motorisés.

## Prérequis

- Jeedom 4.2 ou supérieur
- Contrôleur Niko Home Control II (Connected Controller)
- API Hobby activée sur votre contrôleur
- Connexion réseau entre Jeedom et le contrôleur Niko

## Installation

1. Téléchargez le plugin depuis le Market Jeedom ou installez-le manuellement
2. Activez le plugin dans la gestion des plugins
3. Les dépendances s'installent automatiquement (bibliothèque Python nhc2-coco)

## Configuration

### Configuration du plugin

1. Allez dans **Plugins → Protocole domotique → Niko Home Control II**
2. Cliquez sur **Configuration**
3. Renseignez les paramètres de connexion :
   - **Adresse IP du contrôleur** : IP de votre contrôleur Niko (ex: 192.168.1.100)
   - **Port** : 8884 (port par défaut)
   - **Nom d'utilisateur** : hobby (par défaut)
   - **Mot de passe** : mot de passe que vous avez défini dans l'API Hobby

### Configuration de l'API Hobby sur le contrôleur Niko

1. Connectez-vous à l'interface web de votre contrôleur Niko
2. Allez dans **Paramètres système**
3. Activez **API Hobby** 
4. Définissez un **mot de passe** sécurisé
5. **Redémarrez** votre contrôleur
6. Testez la connexion depuis la configuration du plugin

## Utilisation

### Découverte automatique des équipements

1. Assurez-vous que le démon est démarré (statut vert dans la configuration)
2. Cliquez sur **Synchroniser** dans la liste des équipements
3. Les volets/stores sont automatiquement découverts et ajoutés

### Commandes disponibles pour les volets

Chaque volet dispose des commandes suivantes :

#### Commandes info
- **Position** : Position actuelle du volet (0-100%)
- **État** : État du volet (ouvert/fermé/en mouvement)

#### Commandes action
- **Ouvrir** : Ouvre complètement le volet
- **Fermer** : Ferme complètement le volet  
- **Stop** : Arrête le mouvement en cours
- **Définir position** : Positionne le volet à un pourcentage donné (0-100%)

### Intégration dans les scénarios

Vous pouvez utiliser toutes les commandes dans vos scénarios :

```
// Ouvrir un volet
cmd::byString('#[Salon][Volet Salon][Ouvrir]#')->execCmd();

// Fermer à 50%
cmd::byString('#[Salon][Volet Salon][Définir position]#')->execCmd(array('slider' => 50));

// Condition sur la position
if (cmd::byString('#[Salon][Volet Salon][Position]#')->execCmd() > 80) {
    // Le volet est ouvert à plus de 80%
}
```

## Structure des fichiers

```
plugins/nikohomecontrol2/
├── plugin_info/
│   ├── info.json                 # Informations du plugin
│   ├── install.php              # Script d'installation
│   └── configuration.php        # Interface de configuration
├── core/
│   ├── class/
│   │   ├── nikohomecontrol2.class.php      # Classe principale
│   │   └── nikohomecontrol2Cmd.class.php   # Classe des commandes
│   ├── ajax/
│   │   └── nikohomecontrol2.ajax.php       # Gestionnaire AJAX
│   └── php/
│       └── jeeNikoHC2.php                  # Callback pour le démon
├── desktop/
│   ├── php/
│   │   └── nikohomecontrol2.php            # Interface web
│   └── js/
│       └── nikohomecontrol2.js             # JavaScript de l'interface
├── resources/
│   ├── nikohomecontrold.py                 # Démon Python
│   └── install_apt.sh                      # Script d'installation des dépendances
└── docs/
    └── README.md                          # Cette documentation
```

## Dépannage

### Le démon ne démarre pas

1. Vérifiez que les dépendances sont installées correctement
2. Regardez les logs du plugin : **Analyse → Logs → nikohomecontrol2**
3. Vérifiez la configuration réseau (ping vers le contrôleur)
4. Redémarrez le démon depuis la configuration

### Équipements non découverts

1. Vérifiez que l'API Hobby est bien activée sur le contrôleur
2. Contrôlez les paramètres de connexion (IP, port, mot de passe)
3. Testez la connexion depuis la configuration
4. Regardez les logs pour d'éventuelles erreurs

### Commandes qui ne fonctionnent pas

1. Vérifiez que le démon est bien démarré
2. Testez les commandes manuellement depuis l'interface Niko
3. Regardez les logs pour identifier l'erreur
4. Redémarrez le démon si nécessaire

## Logs

Les logs du plugin se trouvent dans :
- **Logs du plugin** : Analyse → Logs → nikohomecontrol2
- **Logs du démon** : Analyse → Logs → nikohomecontrol2_daemon
- **Logs de mise à jour** : Analyse → Logs → nikohomecontrol2_update

## Support

Pour obtenir de l'aide :
1. Consultez cette documentation
2. Activez les logs en mode DEBUG
3. Consultez le forum communautaire Jeedom
4. Ouvrez un ticket sur GitHub si vous pensez avoir trouvé un bug

## Évolutions futures

- Support des éclairages (on/off et variation)
- Support des prises connectées
- Support des thermostats/chauffage
- Interface mobile optimisée
- Gestion des scenes Niko

## Licence

Ce plugin est distribué sous licence AGPL v3.

## Crédits

Basé sur la bibliothèque Python nhc2-coco et inspiré de l'intégration Home Assistant développée par la communauté Niko.
