# Familjeskärm – Köks-display

## Vad är det här?
En HTML-sida som visas på en skärm i köket (körs på QNAP NAS).
- display.html – själva UI:t
- proxy.php – CORS-proxy för bildlista från QNAP share

## Stack
- Vanilla HTML/CSS/JS, inga frameworks
- State sparas i localStorage (nyckel: kd_v6)
- Fonts: Cormorant Garamond + Jost (Google Fonts)

## Deploy
- Filer redigeras lokalt i C:/CC/Familjeskärmen
- En PostToolUse-hook i .claude/settings.json kopierar display.html och proxy.php till \\Qnap\Web\ automatiskt efter varje Edit/Write
- Hooken är opålitlig — verifiera alltid med diff och kopiera manuellt om det behövs
- Hooken ligger i settings.json (inte settings.local.json) — local-filen skrivs om av permission-hanteraren och tappar hooks
- Produktion: \\Qnap\Web\display.html
- QNAP IP: 192.168.50.38 — webbservern serverar på rot-nivå (http://192.168.50.38/display.html)

## Kioskläge
- Samsung The Frame i köket styrs av en Mac-laptop (stängt lock, sladddrift)
- AppleScript startar Chrome i --kiosk-läge mot http://192.168.50.38/display.html
- caffeinate håller Mac:en vaken
- Lokalt via file:// fungerar inte fullt ut (CORS blockerar proxy.php, bilder, kalender) — bara SL och väder fungerar lokalt

## proxy.php
- act=list — hämtar bildlista från QNAP share.cgi (port 8080 internt)
- act=image — hämtar en specifik bild och skickar vidare med rätt Content-Type
- act=ical — hämtar iCal-URL:er server-side (löser CORS, ersätter corsproxy.io som returnerade 403)
- act=weather — proxar Open-Meteo väder-API server-side (tillagt men display.html använder direkt API-anrop för nu)
- act=rss — hämtar och parsar RSS-flöden, returnerar titlar som JSON (default: SVD)
- act=tibber — proxar Tibber GraphQL API med Bearer-token (CORS blockerar direktanrop från browser)

## Funktioner
- Bildspel från QNAP-share (bilder + video, shuffle, konfigurerbart intervall, object-fit:contain)
- Klocka + datum (svensk locale)
- Väder (Open-Meteo API, hårdkodat Upplands Väsby 59.52/17.91, alla tre dagar uppdelade i 07–12/12–17/17–23 — använder hourly+daily API med weather_code, centrerat under klockan)
- Google Kalender via iCal (familjekalendern hårdkodad, extra kalendrar via inställningar, CORS löses via proxy.php?act=ical, visar 10 kommande händelser)
- SL-avgångar (transport.integration.sl.se v1, inga API-nycklar, ingen rubrik "Avgångar" — sparar plats)
- Elpris (Tibber API via proxy.php?act=tibber + Elon Elnät 26.3 öre/kWh överföring, visar totalpris inkl nät, 24h stapeldiagram med nivåfärger, uppdateras varje timme)
- Sophamtning (hårdkodade datum, popup dagen innan med "Ställ ut!" som försvinner efter 15 sek)
- Nyhetsticker (SVD RSS via proxy.php?act=rss, uppdateras var 15:e minut)
- "Laddad HH:MM D/MM" — diskret text i nedre högra hörnet av bildspelet som visar senaste fullständiga sidladdning
- Inställningspanel (⚙-knapp, sparar i localStorage)

## SL-avgångar
- Fyra hållplatser: Lindhemsvägen (8 min), Truckvägen (6 min), Rondellen (4 min), Väsby stn (20 min)
- travelMin i STOPS-arrayen styr uppskattad restid för "Framme"-kolumnen
- Bussar filtreras mot Upplands Väsby station, tåg filtreras söderut
- Kolumner: ikon, linje, destination, Avgång, Framme, Går om

## Layout
- 70% vänster: bildspel (object-fit:contain, ingen beskärning)
- 30% höger: panel (klocka, väder, kalender, avgångar)

## Automatiska uppdateringar
- Väder: var 30:e minut
- Elpris (Tibber): varje timme
- Nyheter (SVD): var 15:e minut
- Kalender: var 15:e minut
- SL-avgångar: varje minut
- Bildlista: var 30:e minut (nya bilder i mappen plockas upp automatiskt)
- Full sidladdning: var 60:e minut (säkerställer att kodändringar plockas upp)

## Elpris
- Tibber API via proxy.php (GraphQL, Bearer-token)
- Tibber-token: lagras i TIBBER_TOKEN const i display.html
- Elon Elnät överföring: 26.3 öre/kWh inkl moms (21.04 öre exkl) — hårdkodat i ELNAT_ORE
- Visar totalpris (Tibber + nät), 24h stapeldiagram, nivåetiketter (Mycket billigt → Mycket dyrt)
- Kundnummer E.ON: 2386006, 25A abonnemang, Elnätsområde Stockholm, Elområde 3

## Sophamtning
- Datum hårdkodade i WASTE_DATES (matavfall + restavfall) från PDF-schema 2026
- Visar nästa hämtningsdag med countdown, gulmarkerad imorgon, rödmarkerad idag
- Visas mellan kalender och avgångar

## Viktigt: const-deklarationer
- ALLA const som används av init() eller funktioner anropade från init() MÅSTE placeras FÖRE init()-anropet
- Placera dem i blocket mellan `let S = loadState()` och `init()`
- Har orsakat krascher två gånger (W_LAT, WASTE_DATES) pga temporal dead zone

## Planerat
- Live-kamerabild från Reolink Duo 3 WiFi (16MP, 180°, RTSP) i nedre vänstra hörnet — kameran är köpt, ej installerad
- Byta ut Mac-laptop mot Amazon Fire TV Stick 4K Select — ren 4K-output, bättre bildkvalitet, Fully Kiosk Browser
- Spotify "Nu spelas" — behöver ny Spotify Developer-app (rate limited 2026-04-09, retry 2026-04-10), redirect URI: http://127.0.0.1:8888

## Språk
- Svenska genomgående (UI, kommentarer, variabelnamn)
