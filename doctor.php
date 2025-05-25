<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8"/>
  <title>Doctor User Interface - HTML5 Doctor Appointment Scheduling (JavaScript/PHP)</title>

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
        <select id="doctor" name="doctor"></select>
      </div>
      <div id="calendar"></div>
    </div>

  </div>
</div>

<script>

  const app = {
    datepicker: new DayPilot.Navigator("datepicker", {
      selectMode: "Week",
      showMonths: 3,
      skipMonths: 3,
      onTimeRangeSelected: args => {
        app.loadEvents(args.day);
      }
    }),
    calendar: new DayPilot.Calendar("calendar", {
      viewType: "Week",
      timeRangeSelectedHandling: "Disabled",
      eventDeleteHandling: "Update",
      onEventMoved: async args => {
        const {data} = await DayPilot.Http.post("backend_move.php", args);
        app.calendar.message(data.message);
      },
      onEventResized: async args => {
        const {data} = await DayPilot.Http.post("backend_move.php", args);
        app.calendar.message(data.message);
      },
      onEventDeleted: async args => {
        const params = {
          id: args.e.id(),
        };
        await DayPilot.Http.post("backend_delete.php", params);
        app.calendar.message("Deleted.");
      },
      onBeforeEventRender: args => {
        if (!args.data.tags) {
          return;
        }
        switch (args.data.tags.status) {
          case "free":
            args.data.backColor = "#3d85c6";  // blue
            args.data.barHidden = true;
            args.data.borderColor = "darker";
            args.data.fontColor = "white";
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
      onEventClick: async args => {
        const form = [
          {name: "Edit Appointment"},
          {name: "Name", id: "text"},
          {name: "Status", id: "tags.status", options: [
              {name: "Free", id: "free"},
              {name: "Waiting", id: "waiting"},
              {name: "Confirmed", id: "confirmed"},
            ]},
          {name: "From", id: "start", dateFormat: "MMMM d, yyyy h:mm tt", disabled: true},
          {name: "To", id: "end", dateFormat: "MMMM d, yyyy h:mm tt", disabled: true},
          {name: "Doctor", id: "resource", disabled: true, options: app.doctors},
        ];

        const data = args.e.data;

        const options = {
          focus: "text"
        };

        const modal = await DayPilot.Modal.form(form, data, options);
        if (modal.canceled) {
          return;
        }

        const params = {
          id: modal.result.id,
          name: modal.result.text,
          status: modal.result.tags.status
        };

        await DayPilot.Http.post("backend_update.php", params);
        app.calendar.events.update(modal.result);
      }
    }),
    doctors: [],
    elements: {
      doctor: document.querySelector("#doctor")
    },
    async loadEvents(day) {
      const start = app.datepicker.visibleStart();
      const end = app.datepicker.visibleEnd();

      const params = {
        doctor: parseInt(app.elements.doctor.value),
        start: start.toString(),
        end: end.toString()
      };

      const {data} = await DayPilot.Http.post("backend_events_doctor.php", params);

      const options = {
        events: data
      };
      if (day) {
        options.startDate = day;
      }
      app.calendar.update(options);
      app.datepicker.update({events: data});
    },
    addEventHandlers() {
      app.elements.doctor.addEventListener("change", () => {
        app.loadEvents();
      });
    },
    async loadDoctors() {
      const {data} = await DayPilot.Http.get("backend_resources.php");

      app.doctors = data;
      app.doctors.forEach(item => {
        const option = document.createElement("option");
        option.value = item.id;
        option.innerText = item.name;
        app.elements.doctor.appendChild(option);
      });
    },
    async init() {
      app.datepicker.init();
      app.calendar.init();
      app.addEventHandlers();
      await app.loadDoctors();
      await app.loadEvents();
    }
  };
  app.init();

</script>

</body>
</html>
