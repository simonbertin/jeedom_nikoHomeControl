#!/bin/bash
PROGRESS_FILE=$1
if [ ! -z $PROGRESS_FILE ]; then
  touch ${PROGRESS_FILE}
  echo 0 > ${PROGRESS_FILE}
fi
echo "********************************"
echo "*    Installation des dépendances Niko Home Control II    *"
echo "********************************"

echo 10 > ${PROGRESS_FILE}
apt-get clean
echo 20 > ${PROGRESS_FILE}

echo "Installation de Python3 et pip3..."
apt-get update
echo 30 > ${PROGRESS_FILE}

apt-get install -y python3-dev python3-pip python3-setuptools
echo 50 > ${PROGRESS_FILE}

echo "Installation des dépendances Python..."
pip3 install --upgrade pip
echo 60 > ${PROGRESS_FILE}

# Installation de la bibliothèque nhc2-coco
pip3 install nhc2-coco
echo 70 > ${PROGRESS_FILE}

# Installation des autres dépendances
pip3 install requests asyncio
echo 80 > ${PROGRESS_FILE}

# Vérification de l'installation
python3 -c "import nhc2_coco; print('nhc2-coco installé avec succès')"
if [ $? -eq 0 ]; then
    echo "Installation réussie"
    echo 100 > ${PROGRESS_FILE}
else
    echo "Erreur lors de l'installation"
    echo "ERREUR" > ${PROGRESS_FILE}
    exit 1
fi

echo "********************************"
echo "*        Installation terminée        *"
echo "********************************"