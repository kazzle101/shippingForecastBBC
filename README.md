# This is the Shipping Forecast from the BBC

A PHP class to decode the BBC's Shipping Forecast Web Page to a Object for JSON

I quicky knocked this up so I could diplay the Shipping Forecast on a LED matrix pannel, because reasons.

It Converts this: [https://www.bbc.co.uk/weather/coast-and-sea/shipping-forecast](https://www.bbc.co.uk/weather/coast-and-sea/shipping-forecast}

to this:
'''
{
        "from": "2023-12-11T18:00:00.00Z",
        "to": "2023-12-12T18:00:00.00Z",
        "warnings": {
            "title": "Gale warnings are in effect in the following locations: ",
            "areas": [
                "FitzRoy",
                "Sole",
                "Shannon",
                "Rockall",
                "Malin"
            ],
            "issuedAt": "17:25 UTC on 11th Dec"
        },
        "summary": {
            "summary": "Summary at midday",
            "summaryText": "Low 250 miles west of Sole 990 expected south Irish Sea 991 by midday tomorrow",
            "issuedAt": "17:25 UTC on 11th Dec"
        },
        "seaAreas": [
            {
                "seaArea": "Viking",
                "warnings": null,
                "forecast": {
                    "wind": "Variable 2 to 4 becoming southeasterly 3 to 5.",
                    "seaState": "Slight or moderate.",
                    "weather": "Showers.",
                    "visibility": "Good."
                },
                "id": 1
            },
            {
                "seaArea": "North Utsire",
                "warnings": null,
                "forecast": {
                    "wind": "Variable 2 to 4 becoming southeasterly 3 to 5.",
                    "seaState": "Slight or moderate.",
                    "weather": "Showers.",
                    "visibility": "Good."
                },
                "id": 2
            },
            {
                "seaArea": "FitzRoy",
                "warnings": {
                    "title": "Gale warning issued",
                    "text": "Southwesterly gale force 8 continuing",
                    "issuedAt": "15:45 UTC on 11th Dec"
                },
                "forecast": {
                    "wind": "Southwesterly, veering northwesterly later, 5 to 7, occasionally gale 8 in north.",
                    "seaState": "Rough or very rough, becoming high for a time at first in northwest.",
                    "weather": "Thundery showers.",
                    "visibility": "Good, occasionally poor."
                },
                "id": 19
            },
            ... etc ...
'''
(edited for length)

This should work on its own, by calling getShippingForecast() although for me it is part of a larger project.
