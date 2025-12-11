<?php  
 $connect = mysqli_connect("localhost", "root", "", "testing_googlechart");
 if (!empty($_POST["name"])) {
     $name =  $_POST["name"];
    if (!empty($_POST["age"])) {
        $age = $_POST["age"];
       $query = "SELECT age, count(*) as number FROM tbl_employee WHERE age>= $age AND name = '$name' GROUP BY age";
       $query2 = "SELECT name, age FROM tbl_employee WHERE age>= $age AND name = '$name' ORDER BY age ASC";
       echo $name; 
   }
   else{
       $query = "SELECT age, count(*) as number FROM tbl_employee WHERE age>0 GROUP BY age";
       $query2 = "SELECT name, age FROM tbl_employee ORDER BY age ASC"; 
   } 

 }
 else{
    if (!empty($_POST["age"])) {
        $age = $_POST["age"];
       $query = "SELECT age, count(*) as number FROM tbl_employee WHERE age>= $age  GROUP BY age";
       $query2 = "SELECT name, age FROM tbl_employee WHERE age>= $age ORDER BY age ASC"; 
   }
   else{
       $query = "SELECT age, count(*) as number FROM tbl_employee WHERE age>0 GROUP BY age";
       $query2 = "SELECT name, age FROM tbl_employee ORDER BY age ASC"; 
   } 

 }

   
  
 $result = mysqli_query($connect, $query);
 $result2 = mysqli_query($connect, $query);
 $result3 = mysqli_query($connect, $query2);    
 ?>  
 <!DOCTYPE html>  
 <html>  
      <head>  
           <title>MesraDetect : Admin Panel</title>  
           <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>  
           <script type="text/javascript">  
           google.charts.load('current', {'packages':['corechart']});  
           google.charts.setOnLoadCallback(drawChart);
           google.charts.setOnLoadCallback(drawChart2);
           google.charts.load('current', {'packages':['table']});
           google.charts.setOnLoadCallback(drawTable);

           function drawChart()  
           {  
                var data = new google.visualization.arrayToDataTable([  
                          ['age', 'Number'],  
                          <?php  
                          while($row = mysqli_fetch_array($result))  
                          {  
                               echo "['".$row["age"]."', ".$row["number"]."],";  
                          }  
                          ?>  
                     ]);  
                var options = {  
                      title: 'Percentage of Male and Female Employee',  
                      //is3D:true,  
                      // pieHole: 0.4  
                     };  
                var chart = new google.visualization.ColumnChart(document.getElementById('columnchart'));  
                chart.draw(data, options);  
           }
           function drawChart2()  
           {  
                var data = new google.visualization.arrayToDataTable([  
                          ['age', 'Number'],  
                          <?php  
                          while($row = mysqli_fetch_array($result2))  
                          {  
                               echo "['".$row["age"]."', ".$row["number"]."],";  
                          }  
                          ?>  
                     ]);  
                var options = {  
                      title: 'Percentage of Male and Female Employee',  
                      //is3D:true,  
                      // pieHole: 0.4  
                     };  
                var chart = new google.visualization.PieChart(document.getElementById('piechart'));  
                chart.draw(data, options);  
           } 
           
           function drawTable() {
            var data = new google.visualization.arrayToDataTable([  
                          ['name', 'age'],  
                          <?php  
                          while($row = mysqli_fetch_array($result3))  
                          {  
                               echo "['".$row["name"]."', ".$row["age"]."],";  
                          }  
                          ?>  
                     ]); 

        var table = new google.visualization.Table(document.getElementById('table_div'));

        table.draw(data, {showRowNumber: false, align : "center", width: '90%', alternatingRowStyle: true,});
       
      }
    </script>

           </script>  
      </head>  
      <body>  
           <br /><br />
           <h3 align="center">Make Simple Pie Chart by Google Chart API with PHP Mysql</h3> 
           <hr>
<!-- filter -->
           <p>Select Filter</p>
           <form class="form-horizontal" align ="center" action="multi_chart.php" method="post" name="filter" >
           <label for="name">Name:</label>
                <input type ="text" id = "name" name ="name" placeholder="name" >
           <label for="age">From age:</label>
                <input type ="number" id = "age" name ="age" placeholder="age" >
                <input type="submit" name="filter" class="btn btn-success" value="Filter"/>
           </form>
           <hr>

<!-- chart -->
           <div class="container-fluid" align = "center" width ="50%">
                <div class="row">
                    <div class="col-md-4" id="columnchart" style="width: 900px; height: 500px;">
                    </div>
                    <div class="col-md-4" align = "center" id="piechart" style="width: 900px; height: 500px;">
                    </div>
<!-- table -->
                    <div class="col-md-4" id = "table_div">
                    </div>
                </div>
            </div> 
            <br>
            <hr>
            <br>
            
<!-- export to csv -->
            <div  align = "right">
                        <form class="form-horizontal" action="export.php" method="post" name="upload_excel"   
                                enctype="multipart/form-data">
                            <div class="form-group">
                                        <div class="col-md-4 col-md-offset-4">
                                            <input type="submit" name="Export" class="btn btn-success" value="Export to EXCEL"/>
                                        </div>
                            </div>                    
                        </form>           
            </div>

      </body>  
 </html>  