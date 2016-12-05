Feature: Import of labels from UDB2 to UDB3.

  @issue-III-1667
  Scenario: import UDB2 labels with different casing.
    Given an actor in UDB2
    And the actor has 2 labels with only different casing:
    """
    <keywords>
      <keyword>2dotstwice</keyword>
      <keyword>2DOTStwice</keyword>
    </keywords>
    """
    When this actor is imported in UDB3
    Then only the first label gets imported in UDB3
    And the json projection contains label property:
    """
    "labels": {"2dotstwice"}
    """

  @issue-III-1667
  Scenario: import UDB2 label with different casing and visibility.
    Given an actor in UDB2
    And the actor has 2 labels with different casing and different visibility:
    """
    <keywords>
      <keyword visible="false">2dotstwice</keyword>
      <keyword>2DOTStwice</keyword>
    </keywords>
    """
    When this actor is imported in UDB3
    Then only the first label gets imported in UDB3
    And the json projection contains labels:
    """
    "hiddenLabels": {"2dotstwice"}
    """
