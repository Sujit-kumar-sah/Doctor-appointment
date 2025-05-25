<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8"/>
  <title>Manager (Shift Scheduling) Interface - HTML5 Doctor Appointment Scheduling (JavaScript/PHP)</title>

  <link type="text/css" rel="stylesheet" href="css/layout.css"/>
  <link type="text/css" rel="stylesheet" href="css/buttons.css"/>
  <link type="text/css" rel="stylesheet" href="css/toolbar.css"/>

  <!-- DayPilot library -->
  <script src="js/daypilot/daypilot-all.min.js"></script>
</head>
<body>
<?php require_once '_header.php'; ?>

<div class="main">
  <?php require_once '_navigation.php'; ?>

  <div>

    <div class="column-left">
      <div id="datepicker"></div>
    </div>
    <div class="column-main">

      <div class="toolbar">
        <span class="toolbar-item">Scale:
          <label for='scale-hours'><input type="radio" value="hours" name="scale" id='scale-hours' checked> Hours</label>
          <label for='scale-shifts'><input type="radio" value="shifts" name="scale" id='scale-shifts'> Shifts</label></span>
        <span class="toolbar-item"><label for="business-only"><input type="checkbox" id="business-only"> Hide non-business hours</label></span>
        <span class="toolbar-item">Slots: <button id="clear">Clear</button> Deletes all free slots this month</span>
      </div>

      <div id="scheduler"></div>
    </div>

  </div>
</div>

<script>
  const app = {
    datepicker: new DayPilot.Navigator("datepicker", {
      selectMode: "Month",
      showMonths: 3,
      skipMonths: 3,
      onTimeRangeSelected: args => {
        if (app.scheduler.visibleStart().getDatePart() <= args.day && args.day < app.scheduler.visibleEnd()) {
          app.scheduler.scrollTo(args.day, "fast");  // just scroll
        } else {
          app.loadEvents(args.day);  // reload and scroll
        }
      }
    }),
    scheduler: new DayPilot.Scheduler("scheduler", {
      scale: "Manual",
      timeline: [],
      timeHeaders: [],
      cellWidth: 60,
      useEventBoxes: "Never",
      eventDeleteHandling: "Update",
      eventClickHandling: "Disabled",
      eventMoveHandling: "Disabled",
      eventResizeHandling: "Disabled",
      allowEventOverlap: false,
      onBeforeTimeHeaderRender: args => {
        args.header.text = args.header.text.replace(" AM", "a").replace(" PM", "p");  // shorten the hour header
      },
      onBeforeEventRender: args => {
        switch (args.data.tags.status) {
          case "free":
            args.data.backColor = "#3d85c6";  // blue
            args.data.barHidden = true;
            args.data.borderColor = "darker";
            args.data.fontColor = "white";
            args.data.deleteDisabled = app.elements.scale.value === "shifts"; // only allow deleting in the more detailed hour scale mode
            break;
          case "waiting":
            args.data.backColor = "#e69138";  // orange
            args.data.barHidden = true;
            args.data.borderColor = "darker";
            args.data.fontColor = "white";
            break;
          case "confirmed":
            args.data.backColor = "#6aa84f";  // green
            args.data.barHidden = true;
            args.data.borderColor = "darker";
            args.data.fontColor = "white";
            break;
        }
      },
      onEventDeleted: async args => {
        const params = {
          id: args.e.id(),
        };
        await DayPilot.Http.post("backend_delete.php", params);
        app.scheduler.message("Deleted.");
      },
      onTimeRangeSelected: async args => {
        const scale = app.elements.scale.value;

        const params = {
          start: args.start.toString(),
          end: args.end.toString(),
          resource: args.resource,
          scale: scale
        };

        app.scheduler.clearSelection();

        const {data} = await DayPilot.Http.post("backend_create.php", params);
        await app.loadEvents();
        app.scheduler.message(data.message);
      },
    }),
    elements: {
      businessOnly: document.querySelector("#business-only"),
      clear: document.querySelector("#clear"),
      scaleAll: Array.from(document.querySelectorAll("input[name=scale]")),
      get scale() {
        return document.querySelector('input[name=scale]:checked');
      }
    },
    async loadResources() {
      const {data} = await DayPilot.Http.get("backend_resources.php");
      app.scheduler.update({
        resources: data
      });
    },
    async loadEvents(day) {
      let from = app.scheduler.visibleStart();
      let to = app.scheduler.visibleEnd();
      if (day) {
        from = new DayPilot.Date(day).firstDayOfMonth();
        to = from.addMonths(1);
      }

      const params = {
        start: from.toString(),
        end: to.toString()
      };

      const {data} = await DayPilot.Http.post("backend_events.php", params);

      const options = {
        events: data
      };

      if (day) {
        options.timeline = app.getTimeline(day);
        options.scrollTo = day;
      }

      app.scheduler.update(options);
      app.datepicker.update({events: data});
    },
    loadTimeline() {
      app.scheduler.update({
        timeline: app.getTimeline(),
        timeHeaders: app.getTimeHeaders()
      });
    },
    getTimeline(date) {
      date = date || DayPilot.Date.today();
      const start = new DayPilot.Date(date).firstDayOfMonth();
      const days = start.daysInMonth();
      const scale = app.elements.scale.value;
      const businessOnly = app.elements.businessOnly.checked;

      const morningShiftStarts = businessOnly ? 9 : 0;
      const morningShiftEnds = businessOnly ? 13 : 12;
      const afternoonShiftStarts = businessOnly ? 14 : 12;
      const afternoonShiftEnds = businessOnly ? 18 : 24;

      const timeline = [];

      let increaseMorning;  // in hours
      let increaseAfternoon;  // in hours
      switch (scale) {
        case "15min":
          increaseMorning = 0.25;
          increaseAfternoon = 0.25;
          break;
        case "hours":
          increaseMorning = 1;
          increaseAfternoon = 1;
          break;
        case "shifts":
          increaseMorning = morningShiftEnds - morningShiftStarts;
          increaseAfternoon = afternoonShiftEnds - afternoonShiftStarts;
          break;
        default:
          throw "Invalid scale value";
      }

      for (let i = 0; i < days; i++) {
        const day = start.addDays(i);

        for (let x = morningShiftStarts; x < morningShiftEnds; x += increaseMorning) {
          timeline.push({start: day.addHours(x), end: day.addHours(x + increaseMorning)});
        }
        for (let x = afternoonShiftStarts; x < afternoonShiftEnds; x += increaseAfternoon) {
          timeline.push({start: day.addHours(x), end: day.addHours(x + increaseAfternoon)});
        }
      }

      return timeline;
    },
    getTimeHeaders() {
      const scale = app.elements.scale.value;
      switch (scale) {
        case "15min":
          return [
            {groupBy: "Month"},
            {groupBy: "Day", format: "dddd d"},
            {groupBy: "Hour", format: "h tt"},
            {groupBy: "Cell", format: "m"}
          ];
        case "hours":
          return [
            {groupBy: "Month"},
            {groupBy: "Day", format: "dddd d"},
            {groupBy: "Hour", format: "h tt"}
          ];
        case "shifts":
          return [
            {groupBy: "Month"},
            {groupBy: "Day", format: "dddd d"},
            {groupBy: "Cell", format: "tt"}
          ];
      }
    },
    addEventHandlers() {
      app.elements.businessOnly.addEventListener("click", () => {
        app.scheduler.update({
          timeline: app.getTimeline(),
        });
      });

      app.elements.scaleAll.forEach(item => {
        item.addEventListener("change", ev => {
          app.scheduler.update({
            timeline: app.getTimeline(),
            timeHeaders: app.getTimeHeaders()
          });
        });
      });

      app.elements.clear.addEventListener("click", async () => {
        const params = {
          start: app.scheduler.visibleStart(),
          end: app.scheduler.visibleEnd()
        };

        const {data} = await DayPilot.Http.post("backend_clear.php", params);
        await app.loadEvents();
        app.scheduler.message(data.message);
      });

    },
    init() {
      app.datepicker.init();
      app.scheduler.init();
      app.addEventHandlers();
      app.loadTimeline();
      app.loadResources();
      app.loadEvents(DayPilot.Date.today());
    }
  };
  app.init();

</script>

</body>
</html>
