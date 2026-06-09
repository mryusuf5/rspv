# PDF/EPUB upload flow

Dit document beschrijft kort hoe de uploadflow werkt.

1. De frontend stuurt een PDF- of EPUB-bestand naar de backend.
2. De backend ontvangt het bestand via het upload-endpoint.
3. De backend bepaalt of het bestand PDF of EPUB is.
4. De inhoud wordt verwerkt naar tekst/pagina's.
5. Het boek en de pagina's worden opgeslagen.
6. De gebruiker kan daarna lezen via de RSVP-weergave.

Deze flow is belangrijk voor mijn softwarebewijs, omdat hiermee zichtbaar wordt waar frontend en backend samenkomen.
