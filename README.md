# cradle-admin
Manages the Admin Interface

### Admin Dashboard

Shows quick links to help navigate through the admin as well as database table
stats and history.

```
/admin/dashboard
```

### Admin Configuration

Allows editing of configuration variables for the system.

```
/admin/dashboard
```

### Admin Calendar Page

Define a start Date Field *(required)* and an end Date Field *(optional)*.
Then you can visit this page to see a calendar

```
/admin/system/model/class/calendar?show=[start_date],[end_date]
```

### Pipeline (Experimental)

Pipelines are like project boards. Define a Select Field *(to be used for the columns)*, and a Number Field *(to be used for the ordering)*.

```
/admin/system/model/class/pipeline?show=[select_field]&order=[number_field]
```

Optionally you can add a Price Field to total the columns.

```
/admin/system/model/class/pipeline?show=[select_field]&order=[number_field]&currency=USD&range=[price_field_1],[price_field_2]&total=[price_field_1]
```

Lastly if you make a 1:1 relation with profile, you can add a relation in the parameters as well.

```
/admin/system/model/class/pipeline?show=[select_field]&order=[number_field]&currency=USD&range=[price_field_1],[price_field_2]&total=[price_field_1]&relations=profile
```
