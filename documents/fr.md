# Functional Requirements

## Overview

The application is a hydroponic farm management system focused on recording daily operational activities for NFT lettuce farms.

The system supports multiple farms, multiple users within a farm, and multiple nutrient tanks in each farm.

The primary objective of this MVP is to simplify daily monitoring of nutrient conditions while providing historical records and basic operational insights.

---

# 1. Authentication

## Purpose

Authenticate users before accessing the system.

## Features

- Login
- Logout
- Remember Me

---

# 2. Dashboard

## Purpose

Provide an overview of the selected farm.

## Information

Display:

### Farm Summary

- Farm name
- Number of tanks
- Number of active tanks
- Total farm members

### Tank Overview

For every tank display:

- Tank name
- Capacity
- Target PPM
- Target pH
- Latest PPM
- Latest pH
- Last monitoring date
- Active status

Display visual indicator:

- 🟢 Normal
- 🟡 Warning
- 🔴 Critical (future enhancement)

Status is determined by comparing the latest monitoring values against the configured tank target.

### Recent Activities

Display recent activities including:

- Monitoring submitted
- Nutrient addition
- pH Down addition
- Tank created
- Tank updated
- Tank deleted

---

# 3. Farm Management

## Purpose

Allow users to manage hydroponic farms.

A user can own multiple farms.

A farm can have multiple members.

---

## Features

### Create Farm

A user can create a farm.

The creator automatically becomes the owner.

---

### View Farms

Display all farms where the current user is a member.

Display:

- Farm name
- Address
- Number of tanks
- Number of members

---

### Switch Active Farm

Users can switch between farms.

All subsequent operations are performed within the selected farm.

---

### Edit Farm

Only the owner can edit:

- Name
- Address
- Description

---

### Delete Farm

Only the owner can delete a farm.

Deleting a farm removes all related data.

Confirmation is required before deletion.

---

# 4. Farm Members

## Purpose

Allow collaboration between multiple users.

---

## Features

### View Members

Display:

- Name
- Role
- Joined Date

---

### Invite Member

Owner can invite an existing user.

---

### Remove Member

Owner can remove members.

Owner cannot remove themselves.

---

## Permission

### Owner

Can:

- Manage farm
- Delete farm
- Invite members
- Remove members
- Manage tanks
- Record monitoring
- View reports

### Member

Can:

- View farm
- Manage tanks
- Record monitoring
- Record nutrient addition
- Record pH Down
- View reports

Cannot:

- Delete farm
- Manage members

---

# 5. Tank Management

## Purpose

Manage nutrient tanks inside a farm.

---

## Fields

- Name
- Capacity
- Notes
- Active Status

Target Configuration

- Target PPM Minimum
- Target PPM Maximum
- Target pH Minimum
- Target pH Maximum

---

## Features

- Create tank
- Edit tank
- Delete tank
- Activate tank
- Deactivate tank
- View tank detail

---

## Validation

Tank names must be unique within the same farm.

---

# 6. Daily Monitoring

## Purpose

Record daily nutrient conditions.

---

## Fields

- Tank
- Monitoring Date
- PPM
- pH
- Water Temperature (optional)
- Notes

---

## Features

- Create monitoring
- Edit monitoring
- Delete monitoring
- View monitoring history

---

## Validation

PPM

- Minimum 0
- Maximum 3000

pH

- Minimum 0
- Maximum 14

Only one monitoring record is allowed for the same tank on the same day.

---

## Target Validation

Before saving monitoring data:

The system compares:

- Current PPM
- Current pH

against the configured target range of the selected tank.

If the value is outside the configured target:

Display a warning dialog.

Example:

> PPM is below the configured target.
>
> Current PPM : 720
>
> Target : 800 - 900
>
> It is recommended to add nutrient solution.
>
> Do you want to save this monitoring record?

The user may:

- Cancel
- Save Anyway

This validation **does not prevent saving**.

It only provides operational guidance.

---

# 7. Nutrient Addition

## Purpose

Record every nutrient adjustment.

---

## Fields

- Tank
- Date
- PPM Before
- PPM After
- Nutrient A (ml)
- Nutrient B (ml)
- Notes

---

## Features

- Create log
- Edit log
- Delete log
- View history

---

## Validation

PPM After must be greater than PPM Before.

---

# 8. pH Down Log

## Purpose

Record every pH correction.

---

## Fields

- Tank
- Date
- pH Before
- pH After
- pH Down Used (ml)
- Notes

---

## Features

- Create log
- Edit log
- Delete log
- View history

---

## Validation

pH After must be lower than pH Before.

---

# 9. Tank Detail

## Purpose

Provide complete information for a selected tank.

---

## Sections

### Tank Information

- Name
- Capacity
- Status

### Target Configuration

Display:

- Target PPM Range
- Target pH Range

---

### Latest Condition

Display:

- Latest PPM
- Latest pH
- Last monitoring date

---

### Monitoring History

List all monitoring records.

---

### Nutrient Addition History

List all nutrient additions.

---

### pH Down History

List all pH Down logs.

---

# 10. Reports

## Monitoring Report

### Filters

- Tank
- Date Range

### Output

- Average PPM
- Highest PPM
- Lowest PPM
- Average pH
- Highest pH
- Lowest pH
- Number of monitoring records

---

## Nutrient Usage Report

### Filters

- Tank
- Date Range

### Output

- Total Nutrient A Used
- Total Nutrient B Used
- Number of nutrient additions

---

## pH Down Usage Report

### Filters

- Tank
- Date Range

### Output

- Total pH Down Used
- Number of pH adjustments

---

# 11. Activity Logs

## Purpose

Provide an audit trail of system activities.

---

## Automatically Recorded Events

Farm

- Farm Created
- Farm Updated
- Farm Deleted

Tank

- Tank Created
- Tank Updated
- Tank Deleted

Monitoring

- Monitoring Created
- Monitoring Updated
- Monitoring Deleted

Nutrient

- Nutrient Addition Created
- Nutrient Addition Updated
- Nutrient Addition Deleted

pH Down

- pH Down Log Created
- pH Down Log Updated
- pH Down Log Deleted

---

## Information Displayed

- User
- Action
- Entity
- Entity Name
- Date
- Time

Example:

```
Ahmad Ali created Daily Monitoring

Tank A

PPM : 850
pH : 6.2

10 June 2026
08:15
```

---

# Non Functional Requirements

- Responsive design (desktop & mobile)
- Role-based authorization
- Pagination for all list pages
- Search and filtering
- Form validation
- Soft delete for business entities (recommended)
- Audit trail for user actions
- Consistent timezone handling
- REST API ready for future mobile application

---

## Catatan

Ada satu fitur yang menurut saya sangat layak ditambahkan meskipun masih dalam MVP, yaitu **"Quick Action Dashboard"**.

Di dashboard, setiap kartu tandon memiliki tombol:

- **+ Daily Monitoring**
- **+ Nutrient Addition**
- **+ pH Down**

Sehingga operator di lapangan tidak perlu membuka menu satu per satu. Cukup memilih tandon, lalu langsung melakukan pencatatan. Fitur kecil ini akan membuat pengalaman penggunaan jauh lebih cepat dan nyaman, terutama jika nanti Anda memiliki banyak tandon yang harus diperiksa setiap hari.
