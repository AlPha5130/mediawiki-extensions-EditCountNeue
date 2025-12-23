#!/bin/bash
for branch in REL1_43 REL1_44 REL1_45 master; do
  git checkout $branch
  sha=$(git show -s --format=%H)
  echo "{\"head\": \"$sha\n\", \"headSHA1\": \"$sha\n\", \"headCommitDate\": \
\"$(git show -s --format=%ct)\", \"branch\": \"$sha\n\", \"remoteURL\": \
\"https://github.com/AlPha5130/mediawiki-extensions-EditCountNeue\"}" | tee gitinfo.json
  echo -e "EditCountNeue: $branch\n$(date -u -d $(git show -s --format=%cI) +%FT%T)\n\n\
$(git show -s --format=%h)" | tee version
  mkdir EditCountNeue
  cp -r i18n src composer.json CODE_OF_CONDUCT.md EditCount.i18n.alias.php \
    EditCount.i18n.magic.php extension.json Gruntfile.js \
    LICENSE README.md version gitinfo.json \
    EditCountNeue/
  tar -czf output/EditCountNeue-$branch.tar.gz EditCountNeue/
  zip -q -r output/EditCountNeue-$branch.zip EditCountNeue/
  rm -r EditCountNeue
done
