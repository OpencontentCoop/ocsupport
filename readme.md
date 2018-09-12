# OpenContent Support

Viene definita una voce di menu "OpenContent Support" in Impostazioni di backend che richiama il modulo ocsupport/dashboard

Il modulo espone i pacchetti installati via composer, leggendo il file composer.lock presente in <document_root>/../composer.lock

Se il file composer.lock non fosse presente, il modulo espone i repository git presenti fra le estensioni.

Per forzare la ricerca di repository git e ignorare la lettura composer.lock è necessario 
richiamare il modulo con la variabile get `force-git-discover`: www.example.com/ocuspport/dashboard?force-git-discover

## Installazione
- Enable extension
- Regenerate autoloads
- Clear ini and template cache
