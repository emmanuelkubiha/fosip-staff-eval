# 📦 Commandes Git essentielles pour FOSIP

## ✅ Checklist Git à faire une seule fois (mise en place initiale)

1. **Ouvre le terminal dans le dossier du projet**
   ```bash
   cd c:\xampp\htdocs\fosip-eval
   ```
   > **Rôle** : Se placer dans le dossier de ton projet avant toute commande Git.  
   > **Quand ?** Toujours avant de travailler avec Git sur ce projet.

2. **Initialise Git dans le projet**
   ```bash
   git init
   ```
   > **Rôle** : Crée le dossier `.git` qui va suivre l'historique de ton projet.  
   > **Quand ?** Une seule fois au début, quand tu démarres le projet.

3. **Configure ton identité Git**
   ```bash
   git config --global user.name "Ton Nom"
   git config --global user.email "ton.email@example.com"
   ```
   > **Rôle** : Définit ton nom et ton email pour signer tes commits.  
   > **Quand ?** Une seule fois sur ton ordinateur (sauf si tu veux changer d'identité).

4. **Ajoute tous les fichiers au suivi Git**
   ```bash
   git add .
   ```
   > **Rôle** : Prépare tous les fichiers pour le prochain commit (sauf ceux ignorés par `.gitignore`).  
   > **Quand ?** Après avoir créé ou modifié des fichiers, avant de faire un commit.

5. **Fais ton premier commit**
   ```bash
   git commit -m "Initial commit: ajout du projet FOSIP"
   ```
   > **Rôle** : Enregistre un "snapshot" de l'état actuel du projet avec un message.  
   > **Quand ?** Après avoir ajouté les fichiers, à chaque étape importante ou modification.

6. **Crée un dépôt sur GitHub**  
   - Va sur [github.com](https://github.com)
   - Clique sur "New repository"
   - Donne un nom (ex: fosip-eval)
   - Ne coche pas "Add README" ni ".gitignore"
   - Clique sur "Create repository"
   > **Rôle** : Prépare l'espace en ligne où tu vas stocker ton projet.  
   > **Quand ?** Une seule fois, avant de connecter ton projet local à GitHub.

7. **Connecte ton projet local à GitHub**
   ```bash
   git remote add origin https://github.com/emmanuelkubiha/fosip-staff-eval.git
   git branch -M main
   ```
   > **Rôle** : Lie ton projet local au dépôt distant sur GitHub (`origin`).  
   > **Quand ?** Une seule fois après avoir créé le dépôt sur GitHub.

8. **Envoie ton code sur GitHub**
   ```bash
   git push -u origin main
   ```
   > **Rôle** : Envoie tous tes commits sur GitHub pour la première fois.  
   > **Quand ?** Après avoir connecté le dépôt, puis à chaque fois que tu veux publier tes changements.

---

## 🔄 Checklist Git à faire régulièrement (workflow quotidien)

- **Voir les fichiers modifiés**
  ```bash
  git status
  ```
  > **Rôle** : Affiche les fichiers modifiés, ajoutés ou supprimés depuis le dernier commit.  
  > **Quand ?** Avant de faire un commit ou un push, pour vérifier ce qui va être pris en compte.

- **Ajouter les fichiers modifiés**
  ```bash
  git add .
  ```
  > **Rôle** : Prépare tous les fichiers modifiés pour le prochain commit.  
  > **Quand ?** Après avoir modifié ou créé des fichiers, avant chaque commit.

- **Faire un commit**
  ```bash
  git commit -m "Description de la modification"
  ```
  > **Rôle** : Enregistre les modifications dans l'historique Git avec un message explicatif.  
  > **Quand ?** À chaque étape importante, correction ou ajout de fonctionnalité.

- **Envoyer sur GitHub**
  ```bash
  git push
  ```
  > **Rôle** : Publie tes commits sur GitHub (sauvegarde en ligne, partage avec l'équipe).  
  > **Quand ?** Après chaque commit, quand tu veux synchroniser ton travail avec GitHub.

- **Récupérer les changements distants**
  ```bash
  git pull
  ```
  > **Rôle** : Récupère les modifications faites sur GitHub par toi ou d'autres membres.  
  > **Quand ?** Avant de commencer à travailler, ou avant de faire un push si tu travailles à plusieurs.

---

## 🔗 **Connexion à GitHub**

- **Pourquoi ?**  
  Pour synchroniser ton projet local avec le dépôt distant sur GitHub et collaborer avec d'autres.

- **Comment ?**  
  Utilise la commande `git remote add origin ...` une seule fois, puis `git push` pour publier tes commits.

---

## 📝 **Publier un commit en ligne**

1. Modifie tes fichiers localement
2. Dans le terminal :
   ```bash
   git add .
   git commit -m "Description de la modification"
   git push
   ```
   > **Pourquoi ?**  
   Pour enregistrer tes changements et les rendre accessibles sur GitHub.

3. Va sur GitHub pour vérifier que tes changements sont publiés

---

## 💡 **Astuce**

- Utilise le terminal intégré de VS Code pour toutes ces commandes
- Vérifie que `.gitignore` protège bien tes fichiers sensibles (`config.php`, photos uploadées, etc.)
- Toujours faire un `git pull` avant de `git push` si tu travailles à plusieurs ou si tu modifies aussi sur GitHub

---

## ❗ Résoudre l'erreur "failed to push some refs to ..."

### Causes possibles :
- Tu as fait des commits en ligne sur GitHub qui ne sont pas sur ton PC
- La branche distante a des changements que tu n'as pas localement
- Tu essaies de push sur une branche qui n'existe pas ou qui a divergé

### Solution rapide :

1. **Récupère d'abord les changements distants :**
   ```bash
   git pull origin main --rebase
   ```
   > **Pourquoi ?**  
   Pour intégrer les changements du serveur dans ta branche locale et éviter les conflits.

2. **Résous les éventuels conflits (Git te les indique)**
   - Modifie les fichiers concernés
   - Ajoute les fichiers corrigés :
     ```bash
     git add .
     ```
   - Termine le rebase :
     ```bash
     git rebase --continue
     ```

3. **Re-push ensuite :**
   ```bash
   git push origin main
   ```
   > **Pourquoi ?**  
   Pour publier tes changements après avoir résolu les conflits.

---

### Si tu veux forcer le push (⚠️ attention, cela écrase les changements distants) :

```bash
git push --force origin main
```
> **À utiliser seulement si tu es sûr de ne pas perdre de travail sur GitHub !**  
> **Quand ?** Cas exceptionnel, si tu veux écraser la branche distante (risque de perte de données).

---

## ❗ Résoudre l'erreur "fatal: the current branch and set the remote as upstream..."

### Cause :
- Tu as créé la branche `main` en local, mais elle n'est pas encore liée à la branche distante sur GitHub.

### Solution :

1. **Utilise cette commande pour lier ta branche locale à la branche distante :**
   ```bash
   git push --set-upstream origin main
   ```
   > **Rôle** : Définit la branche distante `main` comme "upstream" pour ta branche locale.  
   > **Quand ?** La première fois que tu pushes une nouvelle branche sur GitHub.

2. **Ensuite, tu pourras utiliser simplement :**
   ```bash
   git push
   ```
   > **Pourquoi ?** Après avoir lié la branche, `git push` et `git pull` fonctionneront sans options supplémentaires.

---

**En résumé :**  
Si tu vois cette erreur, lance `git push --set-upstream origin main` une seule fois, puis continue normalement avec `git push` et `git pull`.

---

**Ce fichier est ton pense-bête Git pour le projet FOSIP !**
