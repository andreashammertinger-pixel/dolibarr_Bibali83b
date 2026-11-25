# Jezici i tekstovi

Svi dokumenti vlasnicima (zaduženje, objašnjenje, obračun, e-pošta, plan pričuve) izrađuju se na **hrvatskom** i dodatno na **jeziku vlasnika**. Tekstovi se pišu na izvornom jeziku (uglavnom njemački) i prevode pomoću AI; to sitno piše u podnožju („AI prijevod s jezika: njemački“). Jezik se preuzima od poslovnog partnera (apartmana) → polje *Zadani jezik*.

## Stranica Jezici

*Upravljanje zgradom → Jezici* prikazuje po jeziku:

- broj apartmana s tim jezikom,
- jesu li svi **tekstovi dokumenata** prevedeni,
- jesu li **vrste troškova i stavke plana** prevedene.

## Promjena teksta

1. **Prevedi** kod željenog jezika.
2. Promijeniti tekst u desnom stupcu (lijevo su njemački i hrvatski kao predložak), *Spremi*.

Vlastiti tekstovi spremaju se u bazu i ostaju sačuvani pri ažuriranju modula. Tekstovi objašnjenja počinju s `HvInfo…`.

!!! warning "Rezervirana mjesta"
    `%s` u tekstu zamjenjuje se iznosima, datumima itd. – ne brisati i zadržati redoslijed.

## Novi jezik

1. Kod apartmana postaviti zadani jezik (npr. talijanski) – jezik se automatski pojavljuje na stranici Jezici.
2. Tekstove prevesti izravno **ili** *Izvoz za prevoditelja* → prevesti datoteku → *Uvoz datoteke prijevoda*.

Dok tekstovi nedostaju, koristi se engleski.

## Pojmovi u Dolibarru

Dolibarr općenito govori o *trećim osobama*, *kupcima* i *izlaznim računima*. Na stranici Jezici gumb **Preuzmi pojmove upravljanja zgradom** postavlja odgovarajuće pojmove – samo za ovu tvrtku, na svim jezicima, i za vlasnike:

| Dolibarr | Upravljanje zgradom |
|---|---|
| Treće osobe | Apartmani i kontakti |
| Kupci | Apartmani |
| Kontakti/adrese | Vlasnici i kontakti |
| Računi \| Plaćanja | Zaduženja \| Plaćanja |
| Izlazni računi | Zaduženja |
| Uplate kupaca | Uplate vlasnika |
| Oznaka kupca | Br. apartmana |
| Prodajni predstavnici | Zadužena osoba |

*Mogući kupci* se skrivaju. Pojmovi ostaju sačuvani pri ažuriranju Dolibarra; **Vrati** vraća pojmove Dolibarra. Pojedine pojmove moguće je prilagoditi pod *Postavke → Prijevod*.

## AI prijevod

S API ključem (Postavke → *AI prijevod*) Dolibarr automatski prevodi tekstove za dokumente:

- **Novi tekstovi** (nova vrsta troška, nova stavka plana, novi redak u *Uredi stavke plana*) pri spremanju se prevode na sve jezike dokumenata.
- **Promijenjeni postojeći tekstovi**: pri spremanju Dolibarr pita **„Treba li promjenu preuzeti i na ostalim jezicima?“** – *Da, prevedi* ili *Ne, samo ovaj jezik*.
- **napisano na**: jezik na kojem pišete (unaprijed postavljen Vaš jezik). Svaki djelatnik piše na svom jeziku.
- Ručno ispravljeni prijevodi ne prepisuju se pri istom spremanju.
- **Stranica Jezici → Prevedi**: promijenjeni tekstovni blokovi po želji se preuzimaju na sve jezike; **Automatski prevedi nedostajuće tekstove** jednim klikom popunjava novi jezik (npr. talijanski).

!!! warning "Bez osobnih podataka"
    Šalju se samo sami tekstovi. U ta polja ne upisujte imena, OIB ili bankovne podatke.

Ako usluga nije dostupna, tekst se ipak sprema; poruka na to upozorava.

## Napomena o podrijetlu na dokumentima

Svaki dokument (zaduženje, objašnjenje, obračun, prikaz za ispis plana pričuve, prilozi prevedeni pomoću AI) i svaka poruka dolje sitno sadrži: **izrađeno modulom, verziju i vrijeme**, **izvorni jezik** i napomenu da su **verzije na drugim jezicima prevedene pomoću AI**. Izvorni jezik i napomena: *Postavke*.
