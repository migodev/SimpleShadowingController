# Einfache-Beschattungs-Steuerung
Das Modul erlaubt es auf Basis der Innentemperatur eines Raumes eine Steuervariable zu schalten, um darüber die Beschattung des Raumes zu ermöglichen.
Damit wird der Raum erst dann beschattet, wenn die Soll-Temperatur erreicht ist.
Weiterhin ist optional auch die Beschattung bei Kälte auszusetzen, bis es wieder wärmer ist.

Im Vergleich zu unserem Raum-Beschattungs-Steuerung Modul kann mit diesem Modul auch die gesamte Beschattung realisiert werden, d.h. es werden Azimut & Helligkeitswerte berücksichtigt sowie Rollladen/Raffstores direkt angesteuert.
Hierzu können die diversen Parameter einzeln konfiguriert werden.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Konfiguration](#6-konfiguration)
7. [Visualisierung](#7-visualisierung)
8. [PHP-Befehlsreferenz](#8-php-befehlsreferenz)


### 1. Funktionsumfang

* Überwacht die Innentemperatur mit Soll & Ist sowie den globalen Beschattungsstatus.
* Kann optional auch bei Kälte die Beschattung deaktivieren
* Wertet Helligkeit und Azimut zur Steuerung aus
* Steuert Rollläden/Raffstores direkt an

### 2. Voraussetzungen

- IP-Symcon ab Version 8.1

### 3. Software-Installation

* Über den Module Store das 'Einfache Beschattungs-Steuerung'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen: https://github.com/migodev/SimpleShadowingController

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Einfache Beschattungs-Steuerung'-Modul mithilfe des Schnellfilters gefunden werden.  
    - Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

### 5. Statusvariablen und Profile

Es werden keine Profile angelegt.
Es werden folgende Statusvariablen angelegt:

Name                            | Typ				  | Funktion
------------------------------- | ------------------- | -------------------
Raum Beschattung aktiv          | Boolean		      | Aktiviert bzw. Deaktiviert die Statusvariable für die Beschattung in der Instanz
Beschattung bei Kälte           | Boolean             | Deaktiviert: Außentemperatur wird ignoriert, Aktiviert: Sobald die Außentemperatur unter den Schwellwert fällt, wird die Beschattung deaktiviert bzw. wieder aktiviert, wenn es wärmer wird.
Auswertung Innentemperaturen    | Boolean             | Aktiviert die Innenraum Temperatur-Steuerung, ist der Schalter deaktiviert, synchronisiert sich der Status der Instanz nur mit der globalen Variable.
Pause zwischen 2 Bewegungen     | Integer             | Anzahl an Minuten die zwischen 2 Bewegungen am Rollladen/Raffstore gewartet wird
Grenzwert Helligkeit            | Integer             | Helligkeit in Lux ab der die Beschattung auslöst
Status                          | Boolean             | Zeigt den aktuellen Beschattungsstatus an, true bedeutet es wird gerade beschattet


### 6. Konfiguration

| Eigenschaft                                           |   Typ   | Standardwert | Funktion                                                  |
|:------------------------------------------------------|:-------:|:-------------|:----------------------------------------------------------|
| Positionsvariablen für Rollladen/Raffstores           | string  | []           | Liste der Posotionsvariablen. Es werden nur Variablen vom Typ Integer unterstützt. |
| Azimut Von Wert                                       | integer | 0            | Ab diesem Wert wird beschattet. |
| Azimut Bis Wert                                       | integer | 0            | Bis zu diesem Wert wird beschattet. |
| Azimut Variable                                       | integer | 0            | Azimut Variable unter Kern-Instanzen/Standort |
| Helligkeits Variable                                  | integer | 0            | Helligkeits Variable zum Abgleich des Grenzwerts. In der Regel eine Variable einer Wetterstation |
| Variable für globalen Beschattungsstatus              | integer | 0            | Die globale Variable die die globale Beschattung steuert. In der Regel unter Allgemein/Beschattung/Aktivierung globale Beschattung |
| Variable für automatische Rollosteuerung              | integer | 0            | Kann optional gesetzt werden, wenn eine globale Steuervariable für die Rollladen/Raffstores existiert, damit wird sichergestellt, dass die Beschattung nur funktioniert, wenn die Rollladen sich bewegen sollen. |
| Prozent Beschattung                                   | integer | 0            | Prozentwert auf welchen bei Beschattung der Rollladen/Raffstore gefahren werden soll. |
| Prozent Öffnung                                       | integer | 0            | Prozentwert auf welchen nach der Beschattung der Rollladen/Raffstore gefahren werden soll. |
| Ignoriere Rollladen/Raffstores oberhalb folgender Prozent | integer | 0        | Befindet sich ein Rollladen/Raffstore über diesem angegebenem Prozentwert, wird er von der Automatik ignoriert. Bsp. weil der Rollladen in der Mittagszeit auf 100% geschlossen ist. |
| Bewegungsmodus                                        | integer | 0            | Es kann unterschieden werden ob nur geschlossen wird oder auch wieder geöffnet wird. Standardmäßig wird hoch und herunterfahren. |
| Variable für aktuelle Raumtemperatur                  | integer | 0            | Die Variable welche die aktuelle Ist-Temperatur speichert. |
| Variable für Ziel Raumtemperatur                      | integer | 0            | Die Variable welche die aktuelle Soll-Temperatur speichert. |
| Schwellwert für Außentemperatur                       | integer | 10           | Definiert den Schwellwert ab wann die Beschattung von wegen Kälte deaktiviert wird. |
| Variable für Außentemperatur                          | integer | 0            | Kann jede beliebige Variable sein, welche eine Außentemperatur abbildet. In der automatischen Zuordnung wird hier eine Eltako Wetterstation gesucht und hinterlegt. |
| <em>Action-Center</em>                                |  		  |              |  														 |
| Automatisch Zuordnen                                  |         |              | Via Klick wird die globale Beschattungs-Steuerung Variable gesucht sowie im aktuellen Raum das Eltako FHK14 und die Variablen automatisch vorbefüllt. |
| Reset Pause                                           |         |              | Via Klick wird die Pause zwischen 2 Bewegungen resettet |

### 7. Visualisierung

Das Modul bietet in der Visualisierung die Möglichkeit den Modus, die Beschattung bei Kälte an & auszuschalten, die Pause zu definieren, den Innentemperaturmodus zu aktivieren/deaktivieren und den Beschattungsstatus anzuzeigen

### 8. PHP-Befehlsreferenz

* Über die Methode SCSM_ImportVariables wird das automatische Zuordnen der Variablen ausgelöst
* Über die Methode SCSM_resetPause wird die Pause resettet