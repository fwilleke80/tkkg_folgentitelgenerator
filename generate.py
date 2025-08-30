import re
import json
import random


def loadJson(path: str):
    try:
        with open(path, 'r', encoding='utf-8') as jsonFile:
            return json.load(jsonFile)
    except Exception as e:
        print(f"Error loading JSON file: {e}")
        return None


def processPhrase(phrase: str, replacementsJsonData) -> str:
    def replacer(match: re.Match) -> str:
        placeholder = match.group(1)  # text inside brackets
        
        word = random.choice(replacementsJsonData[placeholder])
        
        # If the replacement word itself contains brackets, process it recursively
        if "[" in word and "]" in word:
            return processPhrase(word, replacementsJsonData)
        else:
            return word
    return re.sub(r"\[(.*?)\]", replacer, phrase)


def main() -> None:
    # Load data
    jsonData = loadJson("./tkkg_data.json")
    if jsonData is None:
        return

    # Randomly choose template to get things started
    template: str = random.choice(jsonData["templates"])

    # Process template
    jsonAssetsData: dict = jsonData["assets"]
    phrase: str = processPhrase(template, jsonAssetsData)

    # Output final title
    print(f"Final generated title: {phrase}")


if __name__ == "__main__":
    main()